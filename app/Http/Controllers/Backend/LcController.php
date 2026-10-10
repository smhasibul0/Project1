<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CostCategory;
use App\Models\Lc;
use App\Models\LcCost;
use App\Models\LcPayment;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Support\DollarLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LcController extends Controller
{
    /**
     * The list of one type — LCs, CADs or TTs.
     */
    public function index(?string $type = null)
    {
        $type ??= 'lc';
        $lcs = Lc::with(['order', 'payments'])->where('type', $type)->latest()->get();

        // Dollar bank charges with no bank rate behind them — estimated or not counted.
        $needsRate = $lcs->filter(fn (Lc $lc) => $lc->needsBankRate())->values();

        return view('admin.backend.lc.lc', [
            'lcs' => $lcs,
            'needsRate' => $needsRate,
            'type' => $type,
            'typeLabel' => Lc::types()[$type],
            'typeTitle' => Lc::typeTitles()[$type],
        ]);
    }

    public function create(Request $request)
    {
        $lc = null;
        $order = $request->filled('order_id') ? Order::find($request->input('order_id')) : null;
        $type = array_key_exists((string) $request->input('type'), Lc::types()) ? $request->input('type') : 'lc';

        return view('admin.backend.lc.create', array_merge($this->formData(), compact('lc', 'order', 'type')));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['type'] = $data['type'] ?? 'lc';
        $data['bank_charges'] = $this->bankCharges($data);
        $data['pi_document'] = $this->uploadDocument($request);
        $data['added_by'] = Auth::id();

        $lc = Lc::create($data);
        $lc->order?->recomputeFinancials();

        return redirect()->route('lc.index', $lc->type)->with('success', $lc->typeLabel().' '.$lc->lc_code.' created successfully.');
    }

    public function show($id)
    {
        $lc = Lc::with(['order.customer', 'addedBy', 'costs.category', 'costs.paymentAccount', 'payments.paymentAccount'])->findOrFail($id);

        return view('admin.backend.lc.show', [
            'lc' => $lc,
            'statuses' => Lc::statuses(),
            'costCategories' => CostCategory::where('is_active', true)->orderBy('name')->get(),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit($id)
    {
        $lc = Lc::findOrFail($id);
        $order = $lc->order;

        return view('admin.backend.lc.edit', array_merge($this->formData(), compact('lc', 'order')));
    }

    public function update(Request $request, $id)
    {
        $lc = Lc::findOrFail($id);
        $data = $this->validated($request);
        $data['bank_charges'] = $this->bankCharges($data);

        // Left out, the type stays as it is.
        if (empty($data['type'])) {
            unset($data['type']);
        }

        if ($document = $this->uploadDocument($request)) {
            $data['pi_document'] = $document;
        }

        $previousOrderId = $lc->order_id;
        $lc->update($data);

        // Recompute both sides when the LC moves between orders (or is unlinked).
        if ($previousOrderId && (int) $previousOrderId !== (int) $lc->order_id) {
            Order::find($previousOrderId)?->recomputeFinancials();
        }
        $lc->order?->recomputeFinancials();

        return redirect()->route('lc.index', $lc->type)->with('success', $lc->typeLabel().' '.$lc->lc_code.' updated successfully.');
    }

    public function destroy($id)
    {
        $lc = Lc::findOrFail($id);

        // Its payments moved real money; they are reversed one by one, not dropped with it.
        if ($lc->payments()->exists()) {
            return redirect()->back()->with('error', $lc->lc_code.' has payments — reverse them before deleting it.');
        }

        $order = $lc->order;
        $lc->delete();
        $order?->recomputeFinancials();

        return redirect()->back()->with('success', $lc->typeLabel().' '.$lc->lc_code.' deleted successfully.');
    }

    /**
     * Update just the LC status from the view page. Stamps the released date
     * the first time it is marked released.
     */
    public function updateStatus(Request $request, $id)
    {
        $lc = Lc::findOrFail($id);

        $data = $request->validate([
            'lc_status' => 'required|in:'.implode(',', array_keys(Lc::statuses())),
            'released_date' => 'nullable|date',
        ]);

        $updates = ['lc_status' => $data['lc_status']];

        if ($data['lc_status'] === 'released' && ! $lc->released_date) {
            $updates['released_date'] = $data['released_date'] ?? now()->toDateString();
        } elseif (! empty($data['released_date'])) {
            $updates['released_date'] = $data['released_date'];
        }

        $lc->update($updates);

        return redirect()->back()->with('success', $lc->lc_code.' status updated to '.$lc->statusLabel().'.');
    }

    /**
     * Record an LC charge line (the "Add LC Charge" action). When a payment account is
     * chosen, it is paid out of that account: decrement its balance + write a debit
     * Transaction into the ledger. LC charges feed the linked order's cost/profit.
     */
    public function storeCost(Request $request, $id)
    {
        $lc = Lc::findOrFail($id);

        $data = $request->validate(DollarLedger::withRules([
            'cost_category_id' => 'nullable|exists:cost_categories,id',
            'title' => 'required|string|max:255',
            'amount' => DollarLedger::amountRule(),
            'cost_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]));

        $data = DB::transaction(function () use ($lc, $data, $request) {
            // Paid in dollars, the charge is what those dollars cost the account.
            $inDollars = DollarLedger::inDollars($data);
            $data['amount'] = $inDollars
                ? DollarLedger::costOf((int) $data['payment_account_id'], (float) $data['usd_amount'])
                : (float) $data['amount'];
            $data['usd_amount'] = $inDollars ? (float) $data['usd_amount'] : null;
            $data['usd_rate'] = $inDollars ? round($data['amount'] / $data['usd_amount'], 4) : null;

            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/lc');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $cost = $lc->costs()->create([
                'cost_category_id' => $data['cost_category_id'] ?? null,
                'title' => $data['title'],
                'amount' => $data['amount'],
                'usd_amount' => $data['usd_amount'],
                'usd_rate' => $data['usd_rate'],
                'cost_date' => $data['cost_date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if ($inDollars) {
                DollarLedger::spend((int) $data['payment_account_id'], (float) $data['usd_amount'], 'lc_cost', [
                    'entry_date' => $cost->cost_date,
                    'description' => $data['title'].' for '.$lc->lc_code,
                    'reference' => $lc->lc_number ?: $lc->lc_code,
                    'note' => $data['note'] ?? null,
                ], $cost);
            } elseif (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'lc_cost',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => $data['title'].' for '.$lc->lc_code,
                    'reference' => $lc->lc_number ?: $lc->lc_code,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => LcCost::class,
                    'transactionable_id' => $cost->id,
                    'added_by' => Auth::id(),
                    'created_at' => $cost->cost_date,
                ]);
            }

            $lc->order?->recomputeFinancials();

            return $data;
        });

        return redirect()->back()->with('success', $lc->typeLabel().' charge of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete an LC charge, reversing its ledger entry when it was paid from an account.
     */
    public function destroyCost($id, $costId)
    {
        $lc = Lc::findOrFail($id);
        $cost = $lc->costs()->findOrFail($costId);

        DB::transaction(function () use ($lc, $cost) {
            // Paid in dollars, they go back to the account; otherwise its taka does.
            if ($cost->payment_account_id && ! DollarLedger::reverseFor($cost)) {
                $account = PaymentAccount::find($cost->payment_account_id);
                if ($account) {
                    $account->increment('balance', (float) $cost->amount);
                }
                Transaction::where('transactionable_type', LcCost::class)
                    ->where('transactionable_id', $cost->id)
                    ->delete();
            }

            $cost->delete();
            $lc->order?->recomputeFinancials();
        });

        return redirect()->back()->with('success', $lc->typeLabel().' charge deleted.');
    }

    /**
     * Bank charges are the difference between the amount sent and the counterpart received.
     *
     * @param  array<string, mixed>  $data
     */
    /**
     * Pay dollars against the LC from a bank account — all of what's due, or a part.
     * The account gives up dollars × the bank's rate in taka and keeps the dollars, to
     * pay costs abroad or sell later. The payment itself is no cost to the order.
     */
    public function storePayment(Request $request, $id)
    {
        $lc = Lc::with('payments')->findOrFail($id);

        if (! $lc->isDollar()) {
            return redirect()->back()->with('error', 'Only '.$lc->typeLabel().'s in US dollars are paid here.');
        }

        $data = $request->validate([
            'paid_on' => 'required|date',
            'payment_account_id' => 'required|exists:payment_accounts,id',
            'usd_amount' => 'required|numeric|min:0.01',
            'bank_rate' => 'required|numeric|min:0.0001|max:100000',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        if ((float) $data['usd_amount'] > $lc->usdDue() + 0.005) {
            return redirect()->back()->withInput()
                ->with('error', 'Only $'.number_format($lc->usdDue(), 2).' is still due on '.$lc->lc_code.'.');
        }

        $payment = DB::transaction(function () use ($lc, $data) {
            $payment = $lc->payments()->create($data + [
                'bdt_amount' => LcPayment::takaFor((float) $data['usd_amount'], (float) $data['bank_rate']),
                'added_by' => Auth::id(),
            ]);

            $account = PaymentAccount::findOrFail($data['payment_account_id']);
            $account->decrement('balance', (float) $payment->bdt_amount);
            $account->transactions()->create([
                'type' => 'debit',
                'source' => 'lc_payment',
                'amount' => $payment->bdt_amount,
                'credit' => 0,
                'debit' => $payment->bdt_amount,
                'running_balance' => $account->fresh()->balance,
                'description' => $lc->typeLabel().' payment '.$lc->lc_code.' — $'.number_format((float) $payment->usd_amount, 2).' @ '.$this->rate($payment->bank_rate),
                'reference' => $lc->lc_number ?: $lc->lc_code,
                'payment_details' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'usd_amount' => $payment->usd_amount,
                'usd_rate' => $payment->bank_rate,
                'transactionable_type' => LcPayment::class,
                'transactionable_id' => $payment->id,
                'added_by' => Auth::id(),
                'created_at' => $payment->paid_on,
            ]);

            // The dollars never leave: they are kept in the account, at the bank's rate.
            DollarLedger::receive($account, (float) $payment->usd_amount, (float) $payment->bank_rate, 'lc_payment', [
                'entry_date' => $payment->paid_on,
                'description' => 'Bought with '.$lc->typeLabel().' payment '.$lc->lc_code,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ], $payment, (float) $payment->bdt_amount);

            // The bank charge is converted at the LC's bank rate, which this payment moves.
            $lc->load('payments');
            $lc->order?->recomputeFinancials();

            return $payment;
        });

        return redirect()->back()->with('success', 'Paid $'.number_format((float) $payment->usd_amount, 2).' at ৳'.$this->rate($payment->bank_rate)
            .' — ৳'.number_format((float) $payment->bdt_amount, 2).' from '.$payment->paymentAccount->name
            .'. The dollars are kept in '.$payment->paymentAccount->name.'.');
    }

    /**
     * Undo an LC payment: its dollars leave the account (while it still holds them) and
     * the taka comes back.
     */
    public function destroyPayment($id, $paymentId)
    {
        $lc = Lc::findOrFail($id);
        $payment = $lc->payments()->findOrFail($paymentId);

        DB::transaction(function () use ($lc, $payment) {
            // The dollars it bought must still be in the account to give them back.
            DollarLedger::reverseFor($payment);

            PaymentAccount::find($payment->payment_account_id)?->increment('balance', (float) $payment->bdt_amount);
            Transaction::where('transactionable_type', LcPayment::class)
                ->where('transactionable_id', $payment->id)
                ->delete();

            $payment->delete();
            $lc->load('payments');
            $lc->order?->recomputeFinancials();
        });

        return redirect()->back()->with('success', $lc->typeLabel().' payment of $'.number_format((float) $payment->usd_amount, 2).' reversed.');
    }

    /**
     * A rate as written: 123.1, not 123.1000.
     */
    private function rate(float|string $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 4), '0'), '.');
    }

    private function bankCharges(array $data): float
    {
        if (! isset($data['net_amount_received']) || $data['net_amount_received'] === null) {
            return 0;
        }

        return round((float) ($data['invoice_amount'] ?? 0) - (float) $data['net_amount_received'], 2);
    }

    /**
     * Store the attached purchase-invoice document, returning its filename.
     */
    private function uploadDocument(Request $request): ?string
    {
        if (! $request->hasFile('pi_document')) {
            return null;
        }

        $file = $request->file('pi_document');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
        $dir = public_path('upload/lc');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'orders' => Order::with('customer')->latest()->get(),
            'statuses' => Lc::statuses(),
            'types' => Lc::typeNames(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => 'nullable|in:'.implode(',', array_keys(Lc::types())),
            'order_id' => 'nullable|exists:orders,id',
            'shipper' => 'nullable|string|max:255',
            'pi_date' => 'nullable|date',
            'pi_no' => 'nullable|string|max:255',
            'lc_number' => 'nullable|string|max:255',
            'opening_bank' => 'nullable|string|max:255',
            'container_no' => 'nullable|string|max:255',
            'commodity' => 'nullable|string',
            'invoice_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'net_amount_received' => 'nullable|numeric|min:0',
            'usd_sell_rate' => 'nullable|numeric|min:0',
            'usd_sell_date' => 'nullable|date',
            'lc_status' => 'nullable|in:'.implode(',', array_keys(Lc::statuses())),
            'released_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'pi_document' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);
    }
}
