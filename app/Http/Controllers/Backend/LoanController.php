<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Support\DollarLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    /** @var list<string> */
    public const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Cheque', 'Mobile Banking', 'Other'];

    /**
     * One side of the book: money borrowed from banks and individuals, or
     * money lent out. Both directions share this page — only the wording and
     * the direction the cash moves differ.
     */
    public function index(string $direction)
    {
        abort_unless(array_key_exists($direction, Loan::DIRECTIONS), 404);

        $loans = Loan::with(['payments.dollarEntries', 'payments.paymentAccount', 'account', 'dollarEntries'])
            ->where('direction', $direction)
            ->orderByDesc('start_date')->orderByDesc('id')
            ->get();

        $open = $loans->where('status', 'active');

        return view('admin.backend.finance.loans', [
            'direction' => $direction,
            'loans' => $loans,
            'principalTotal' => round($open->sum(fn (Loan $l) => (float) $l->principal), 2),
            'interestTotal' => round($open->sum(fn (Loan $l) => $l->interestToDate()), 2),
            'paidTotal' => round($open->sum(fn (Loan $l) => $l->paidTotal()), 2),
            'outstandingTotal' => round($open->sum(fn (Loan $l) => $l->outstanding()), 2),
            'overdueCount' => $loans->filter(fn (Loan $l) => $l->isOverdue())->count(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'interestTypes' => Loan::INTEREST_TYPES,
            'counterpartyTypes' => Loan::COUNTERPARTY_TYPES,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Record a new loan. Naming an account moves the principal there and
     * writes it to that account's ledger: money in when borrowing, money out
     * when lending.
     */
    public function store(Request $request, string $direction)
    {
        abort_unless(array_key_exists($direction, Loan::DIRECTIONS), 404);

        $data = $this->validated($request);
        // Borrowed dollars come in at the rate given; lent ones go out at what they cost.
        $dollars = $request->validate(DollarLedger::withRules([], 'payment_account_id', withRate: $direction === 'borrowed'));

        DB::transaction(function () use ($data, $direction, $request, $dollars) {
            if (DollarLedger::inDollars($dollars)) {
                $data['principal'] = $direction === 'borrowed'
                    ? round((float) $dollars['usd_amount'] * (float) $dollars['usd_rate'], 2)
                    : DollarLedger::costOf((int) $data['payment_account_id'], (float) $dollars['usd_amount']);
            }

            $loan = Loan::create($data + [
                'loan_code' => Loan::nextCode($direction),
                'direction' => $direction,
                'attachment' => $this->storeAttachment($request),
                'added_by' => Auth::id(),
            ]);

            if ($loan->payment_account_id) {
                $this->movePrincipal($loan, $dollars);
            }
        });

        return redirect()->back()->with('success', Loan::DIRECTIONS[$direction].' record added.');
    }

    /**
     * Edit a loan's own terms. The principal can never drop below what has
     * already changed hands, and the account the principal sits in is left
     * alone once set — moving it would mean unwinding a posted ledger entry.
     */
    public function update(Request $request, $id)
    {
        $loan = Loan::with('payments')->findOrFail($id);
        $data = $this->validated($request);

        if ((float) $data['principal'] + 0.005 < $loan->paidTotal()) {
            throw ValidationException::withMessages([
                'principal' => 'Principal cannot be less than the '.number_format($loan->paidTotal(), 2).' already settled. Delete a payment first.',
            ]);
        }

        unset($data['payment_account_id']);

        if ($attachment = $this->storeAttachment($request)) {
            $data['attachment'] = $attachment;
        }

        $loan->update($data);

        return redirect()->back()->with('success', 'Loan updated.');
    }

    /**
     * Record an instalment: money repaid on a borrowing, money received back
     * on a lending. Never more than what is still outstanding.
     */
    public function storePayment(Request $request, $id)
    {
        $loan = Loan::with('payments')->findOrFail($id);

        // A repayment of a borrowing goes out in dollars at what they cost; a receipt on
        // a lending comes in at the rate given.
        $data = $request->validate(DollarLedger::withRules([
            'amount' => DollarLedger::amountRule(),
            'paid_on' => 'required|date',
            'method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:255',
        ], 'payment_account_id', withRate: ! $loan->isBorrowed()));
        $inDollars = DollarLedger::inDollars($data);

        $data = DB::transaction(function () use ($loan, $data, $inDollars) {
            if ($inDollars) {
                $data['amount'] = $loan->isBorrowed()
                    ? DollarLedger::costOf((int) $data['payment_account_id'], (float) $data['usd_amount'])
                    : round((float) $data['usd_amount'] * (float) $data['usd_rate'], 2);
            }

            if ((float) $data['amount'] > $loan->outstanding() + 0.005) {
                throw ValidationException::withMessages([
                    $inDollars ? 'usd_amount' : 'amount' => 'That is more than the outstanding '.number_format($loan->outstanding(), 2).'.',
                ]);
            }

            $payment = $loan->payments()->create(Arr::only($data, ['amount', 'paid_on', 'method', 'payment_account_id', 'note']) + ['added_by' => Auth::id()]);

            if (! empty($data['payment_account_id'])) {
                $this->moveInstalment($loan, $payment, $inDollars ? $data : null);
            }

            // A loan paid off in full closes itself rather than waiting to be tidied.
            if ($loan->fresh('payments')->outstanding() <= 0.005) {
                $loan->update(['status' => 'settled']);
            }

            return $data;
        });

        return redirect()->back()->with('success', 'Payment of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete an instalment, putting its account back where it was.
     */
    public function destroyPayment($id, $paymentId)
    {
        $loan = Loan::findOrFail($id);
        $payment = $loan->payments()->findOrFail($paymentId);

        DB::transaction(function () use ($loan, $payment) {
            $this->reverseInstalment($loan, $payment);
            $payment->delete();

            // Reopen a loan that was only settled because of this payment.
            if ($loan->status === 'settled' && $loan->fresh('payments')->outstanding() > 0.005) {
                $loan->update(['status' => 'active']);
            }
        });

        return redirect()->back()->with('success', 'Payment deleted.');
    }

    /**
     * Close a loan by hand — settled early, or written off as unrecoverable.
     */
    public function close(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'settled', 'written_off'])],
            'note' => 'nullable|string|max:1000',
        ]);

        $loan = Loan::findOrFail($id);
        $loan->update(['status' => $data['status'], 'note' => $data['note'] ?? $loan->note]);

        return redirect()->back()->with('success', 'Loan marked as '.Loan::STATUSES[$data['status']].'.');
    }

    /**
     * Delete a loan, reversing the principal and every instalment posted
     * against the accounts.
     */
    public function destroy($id)
    {
        $loan = Loan::with('payments')->findOrFail($id);

        DB::transaction(function () use ($loan) {
            foreach ($loan->payments as $payment) {
                $this->reverseInstalment($loan, $payment);
            }

            $this->reversePrincipal($loan);
            $loan->delete();
        });

        return redirect()->back()->with('success', 'Loan deleted.');
    }

    /**
     * The principal changing hands: borrowing puts money into the account,
     * lending takes it out.
     */
    private function movePrincipal(Loan $loan, ?array $dollars = null): void
    {
        if ($dollars && DollarLedger::inDollars($dollars)) {
            $this->moveDollars($loan, $loan, ! $loan->isBorrowed(), (float) $dollars['usd_amount'], (float) ($dollars['usd_rate'] ?? 0), (float) $loan->principal,
                ($loan->isBorrowed() ? 'Borrowed from ' : 'Lent to ').$loan->counterparty, $loan->start_date?->toDateString(), $loan->note);

            return;
        }

        $this->post(
            $loan,
            $loan->payment_account_id,
            (float) $loan->principal,
            $loan->isBorrowed() ? 'credit' : 'debit',
            $loan->isBorrowed() ? 'loan_received' : 'loan_given',
            ($loan->isBorrowed() ? 'Borrowed from ' : 'Lent to ').$loan->counterparty,
            $loan->start_date?->toDateString(),
            $loan,
        );
    }

    /**
     * An instalment moves the opposite way to the principal: a borrowing is
     * repaid out of the account, a lending is received back into it.
     */
    private function moveInstalment(Loan $loan, LoanPayment $payment, ?array $dollars = null): void
    {
        if ($dollars) {
            $this->moveDollars($loan, $payment, $loan->isBorrowed(), (float) $dollars['usd_amount'], (float) ($dollars['usd_rate'] ?? 0), (float) $payment->amount,
                ($loan->isBorrowed() ? 'Repaid to ' : 'Received from ').$loan->counterparty, $payment->paid_on?->toDateString(), $payment->note);

            return;
        }

        $this->post(
            $loan,
            $payment->payment_account_id,
            (float) $payment->amount,
            $loan->isBorrowed() ? 'debit' : 'credit',
            $loan->isBorrowed() ? 'loan_repayment' : 'loan_receipt',
            ($loan->isBorrowed() ? 'Repaid to ' : 'Received from ').$loan->counterparty,
            $payment->paid_on?->toDateString(),
            $payment,
            $payment->method,
            $payment->note,
        );
    }

    /**
     * Move dollars on the record's account: out at what they cost, or in at the rate given.
     */
    private function moveDollars(Loan $loan, Loan|LoanPayment $record, bool $out, float $usd, float $rate, float $taka, string $description, ?string $date, ?string $note): void
    {
        $details = ['entry_date' => $date, 'description' => $description, 'reference' => $loan->loan_code, 'note' => $note];

        $out
            ? DollarLedger::spend((int) $record->payment_account_id, $usd, 'loan', $details, $record)
            : DollarLedger::receive((int) $record->payment_account_id, $usd, $rate, 'loan', $details, $record, $taka);
    }

    /**
     * Move an amount on an account and write the matching ledger entry.
     */
    private function post(
        Loan $loan,
        int $accountId,
        float $amount,
        string $type,
        string $source,
        string $description,
        ?string $date,
        $transactionable,
        ?string $method = null,
        ?string $note = null,
    ): void {
        $account = PaymentAccount::findOrFail($accountId);

        $type === 'credit'
            ? $account->increment('balance', $amount)
            : $account->decrement('balance', $amount);

        $account->transactions()->create([
            'type' => $type,
            'source' => $source,
            'amount' => $amount,
            'credit' => $type === 'credit' ? $amount : 0,
            'debit' => $type === 'debit' ? $amount : 0,
            'running_balance' => $account->fresh()->balance,
            'description' => $description,
            'payment_method' => $method,
            'reference' => $loan->loan_code,
            'note' => $note,
            'transactionable_type' => $transactionable::class,
            'transactionable_id' => $transactionable->id,
            'added_by' => Auth::id(),
            'created_at' => $date,
        ]);
    }

    /**
     * Undo an instalment's effect on its account.
     */
    private function reverseInstalment(Loan $loan, LoanPayment $payment): void
    {
        $this->unpost($payment, $payment->payment_account_id, (float) $payment->amount, $loan->isBorrowed() ? 'debit' : 'credit');
    }

    /**
     * Undo the principal's effect on its account.
     */
    private function reversePrincipal(Loan $loan): void
    {
        $this->unpost($loan, $loan->payment_account_id, (float) $loan->principal, $loan->isBorrowed() ? 'credit' : 'debit');
    }

    /**
     * Put an account back where it was and drop the ledger entry. The type
     * given is how the money originally moved, so it is applied in reverse.
     */
    private function unpost($transactionable, ?int $accountId, float $amount, string $originalType): void
    {
        // Moved in dollars, they are undone on the account's dollars instead.
        if (! $accountId || DollarLedger::reverseFor($transactionable)) {
            return;
        }

        if ($account = PaymentAccount::find($accountId)) {
            $originalType === 'credit'
                ? $account->decrement('balance', $amount)
                : $account->increment('balance', $amount);
        }

        Transaction::where('transactionable_type', $transactionable::class)
            ->where('transactionable_id', $transactionable->id)
            ->delete();
    }

    /**
     * A loan needs the basis its interest is charged on: a yearly rate for a
     * percentage, a flat figure for a fixed charge, neither when there is no
     * interest at all.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'counterparty' => 'required|string|max:255',
            'counterparty_type' => ['required', Rule::in(array_keys(Loan::COUNTERPARTY_TYPES))],
            'principal' => 'required|numeric|min:0.01',
            'interest_type' => ['required', Rule::in(array_keys(Loan::INTEREST_TYPES))],
            'interest_rate' => 'nullable|required_if:interest_type,percentage|numeric|min:0.01|max:100',
            'interest_amount' => 'nullable|required_if:interest_type,fixed|numeric|min:0.01',
            'start_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        return [
            'counterparty' => $data['counterparty'],
            'counterparty_type' => $data['counterparty_type'],
            'principal' => $data['principal'],
            'interest_type' => $data['interest_type'],
            'interest_rate' => $data['interest_type'] === 'percentage' ? $data['interest_rate'] : null,
            'interest_amount' => $data['interest_type'] === 'fixed' ? $data['interest_amount'] : null,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'] ?? null,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }

    /**
     * Move an uploaded agreement into place, returning its filename.
     */
    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/loans');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        return $name;
    }
}
