<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\OfficeCostType;
use App\Models\OfficeExpense;
use App\Models\OfficeExpensePayment;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficeExpenseController extends Controller
{
    /** @var list<string> */
    public const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Cheque', 'Mobile Banking', 'Other'];

    /**
     * One month of office running costs, split into fixed and variable.
     */
    public function index(Request $request)
    {
        $month = $this->resolveMonth($request->query('month'));

        $expenses = OfficeExpense::with(['costType.category.parent', 'payments.paymentAccount'])
            ->whereBetween('expense_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->latest('expense_date')->latest('id')->get();

        $fixed = $expenses->filter(fn (OfficeExpense $e) => $e->isFixed());
        $variable = $expenses->reject(fn (OfficeExpense $e) => $e->isFixed());

        $sum = fn ($rows) => round($rows->sum(fn (OfficeExpense $e) => (float) $e->amount), 2);
        $paid = round($expenses->sum(fn (OfficeExpense $e) => $e->paidTotal()), 2);
        $total = $sum($expenses);

        // Fixed types with a standard amount that this month hasn't booked yet.
        $pendingFixed = OfficeCostType::generatable()
            ->whereNotIn('id', $expenses->pluck('office_cost_type_id')->filter()->all())
            ->get();

        return view('admin.backend.office.expenses', [
            'month' => $month,
            'expenses' => $expenses,
            'fixedTotal' => $sum($fixed),
            'variableTotal' => $sum($variable),
            'total' => $total,
            'paidTotal' => $paid,
            'outstanding' => round($total - $paid, 2),
            'pendingFixed' => $pendingFixed,
            'pendingFixedTotal' => round((float) $pendingFixed->sum(fn (OfficeCostType $t) => (float) $t->monthly_amount), 2),
            'costTypes' => OfficeCostType::with('category.parent')->where('is_active', true)
                ->orderBy('nature')->orderBy('name')->get(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Create this month's rows for every fixed cost type that carries a
     * standard monthly amount. Re-running is safe: a type that already has an
     * expense in the month is skipped rather than duplicated, so the amounts
     * you have since edited stay as they are.
     */
    public function generate(Request $request)
    {
        $month = $this->resolveMonth($request->input('month'));
        $start = $month->copy()->startOfMonth();

        $alreadyBooked = OfficeExpense::whereBetween('expense_date', [$start, $month->copy()->endOfMonth()])
            ->pluck('office_cost_type_id')
            ->filter()
            ->all();

        $pending = OfficeCostType::generatable()->whereNotIn('id', $alreadyBooked)->get();

        if ($pending->isEmpty()) {
            return redirect()->back()->with('success', 'Nothing to generate — every fixed cost type already has a '.$month->format('F Y').' entry.');
        }

        DB::transaction(function () use ($pending, $start) {
            foreach ($pending as $type) {
                OfficeExpense::create([
                    'office_cost_type_id' => $type->id,
                    'amount' => $type->monthly_amount,
                    'expense_date' => $start,
                    'note' => 'Generated monthly fixed cost',
                    'added_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', $pending->count().' fixed cost(s) added for '.$month->format('F Y').', unpaid. Adjust any amount that differs this month.');
    }

    /**
     * Record an office expense, optionally with an initial payment. The
     * expense keeps its full accrued amount; payments settle it over time.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'office_cost_type_id' => 'required|exists:office_cost_types,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
            'payment_amount' => 'nullable|required_with:payment_account_id|numeric|min:0.01|lte:amount',
            'paid_on' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'payment_note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $expense = OfficeExpense::create([
                'office_cost_type_id' => $data['office_cost_type_id'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'note' => $data['note'] ?? null,
                'attachment' => $this->storeAttachment($request),
                'added_by' => Auth::id(),
            ]);

            if (! empty($data['payment_amount'])) {
                $this->recordPayment($expense, [
                    'amount' => $data['payment_amount'],
                    'paid_on' => $data['paid_on'] ?? $data['expense_date'],
                    'method' => $data['payment_method'] ?? null,
                    'payment_account_id' => $data['payment_account_id'] ?? null,
                    'note' => $data['payment_note'] ?? null,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Expense of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Edit an expense's own details. Payments are managed separately, so the
     * total can never drop below what has already been paid.
     */
    public function update(Request $request, $id)
    {
        $expense = OfficeExpense::with('payments')->findOrFail($id);

        $data = $request->validate([
            'office_cost_type_id' => 'required|exists:office_cost_types,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        if ((float) $data['amount'] + 0.005 < $expense->paidTotal()) {
            throw ValidationException::withMessages([
                'amount' => 'Total cannot be less than the '.number_format($expense->paidTotal(), 2).' already paid. Delete a payment first.',
            ]);
        }

        if ($attachment = $this->storeAttachment($request)) {
            $data['attachment'] = $attachment;
        }

        $expense->update($data);

        return redirect()->back()->with('success', 'Expense updated.');
    }

    /**
     * Add a payment against an existing expense (partial payments allowed,
     * never more than the outstanding due).
     */
    public function storePayment(Request $request, $id)
    {
        $expense = OfficeExpense::with('payments')->findOrFail($id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_on' => 'required|date',
            'method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:255',
        ]);

        if ((float) $data['amount'] > $expense->dueTotal() + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the outstanding due of '.number_format($expense->dueTotal(), 2).'.',
            ]);
        }

        DB::transaction(fn () => $this->recordPayment($expense, $data));

        return redirect()->back()->with('success', 'Payment of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete a single payment, crediting its account back.
     */
    public function destroyPayment($id, $paymentId)
    {
        $payment = OfficeExpense::findOrFail($id)->payments()->findOrFail($paymentId);

        DB::transaction(function () use ($payment) {
            $this->reversePayment($payment);
            $payment->delete();
        });

        return redirect()->back()->with('success', 'Payment deleted.');
    }

    /**
     * Delete an expense, reversing every payment's ledger entry (crediting the
     * accounts back).
     */
    public function destroy($id)
    {
        $expense = OfficeExpense::with('payments')->findOrFail($id);

        DB::transaction(function () use ($expense) {
            foreach ($expense->payments as $payment) {
                $this->reversePayment($payment);
            }

            $expense->delete();
        });

        return redirect()->back()->with('success', 'Expense deleted.');
    }

    /**
     * The month being viewed, defaulting to the current one. Anything
     * unparseable falls back rather than erroring the page.
     */
    private function resolveMonth(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Exception) {
            return Carbon::now()->startOfMonth();
        }
    }

    /**
     * Move an uploaded receipt into place, returning its stored filename.
     */
    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/expenses');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        return $name;
    }

    /**
     * Create a payment row and, when paid from an account, debit that
     * account's ledger.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordPayment(OfficeExpense $expense, array $data): void
    {
        $payment = $expense->payments()->create([
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'method' => $data['method'] ?? null,
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'note' => $data['note'] ?? null,
            'added_by' => Auth::id(),
        ]);

        if (! empty($data['payment_account_id'])) {
            $account = PaymentAccount::findOrFail($data['payment_account_id']);
            $account->decrement('balance', (float) $data['amount']);
            $account->transactions()->create([
                'type' => 'debit',
                'source' => 'office_expense',
                'amount' => $data['amount'],
                'credit' => 0,
                'debit' => $data['amount'],
                'running_balance' => $account->fresh()->balance,
                'description' => 'Office: '.($expense->costType->name ?? 'Expense'),
                'payment_method' => $data['method'] ?? null,
                'reference' => 'Office',
                'note' => $data['note'] ?? null,
                'transactionable_type' => OfficeExpensePayment::class,
                'transactionable_id' => $payment->id,
                'added_by' => Auth::id(),
                'created_at' => $data['paid_on'],
            ]);
        }
    }

    /**
     * Credit a payment's account back and remove its ledger entry.
     */
    private function reversePayment(OfficeExpensePayment $payment): void
    {
        if (! $payment->payment_account_id) {
            return;
        }

        $account = PaymentAccount::find($payment->payment_account_id);
        if ($account) {
            $account->increment('balance', (float) $payment->amount);
        }

        Transaction::where('transactionable_type', OfficeExpensePayment::class)
            ->where('transactionable_id', $payment->id)
            ->delete();
    }
}
