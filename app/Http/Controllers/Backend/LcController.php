<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\CostCategory;
use App\Models\Lc;
use App\Models\LcCost;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LcController extends Controller
{
    public function index()
    {
        $lcs = Lc::with(['order', 'supplier'])->latest()->get();

        return view('admin.backend.lc.lc', compact('lcs'));
    }

    public function create(Request $request)
    {
        $lc = null;
        $order = $request->filled('order_id') ? Order::find($request->input('order_id')) : null;

        return view('admin.backend.lc.create', array_merge($this->formData(), compact('lc', 'order')));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['bank_charges'] = $this->bankCharges($data);
        $data['pi_document'] = $this->uploadDocument($request);
        $data['added_by'] = Auth::id();

        $lc = Lc::create($data);
        $lc->order?->recomputeFinancials();

        return redirect()->route('lc.index')->with('success', 'LC created successfully.');
    }

    public function show($id)
    {
        $lc = Lc::with(['order.customer', 'supplier', 'addedBy', 'costs.category', 'costs.paymentAccount'])->findOrFail($id);

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

        return redirect()->route('lc.index')->with('success', 'LC updated successfully.');
    }

    public function destroy($id)
    {
        $lc = Lc::findOrFail($id);
        $order = $lc->order;
        $lc->delete();
        $order?->recomputeFinancials();

        return redirect()->back()->with('success', 'LC deleted successfully.');
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

        return redirect()->back()->with('success', 'LC status updated to '.$lc->statusLabel().'.');
    }

    /**
     * Record an LC charge line (the "Add LC Charge" action). When a payment account is
     * chosen, it is paid out of that account: decrement its balance + write a debit
     * Transaction into the ledger. LC charges feed the linked order's cost/profit.
     */
    public function storeCost(Request $request, $id)
    {
        $lc = Lc::findOrFail($id);

        $data = $request->validate([
            'cost_category_id' => 'nullable|exists:cost_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'cost_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        DB::transaction(function () use ($lc, $data, $request) {
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
                'cost_date' => $data['cost_date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            if (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'lc_cost',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => $data['title'].' for LC '.$lc->lc_code,
                    'reference' => $lc->lc_number ?: $lc->lc_code,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => LcCost::class,
                    'transactionable_id' => $cost->id,
                    'added_by' => Auth::id(),
                    'created_at' => $cost->cost_date,
                ]);
            }

            $lc->order?->recomputeFinancials();
        });

        return redirect()->back()->with('success', 'LC charge of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete an LC charge, reversing its ledger entry when it was paid from an account.
     */
    public function destroyCost($id, $costId)
    {
        $lc = Lc::findOrFail($id);
        $cost = $lc->costs()->findOrFail($costId);

        DB::transaction(function () use ($lc, $cost) {
            if ($cost->payment_account_id) {
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

        return redirect()->back()->with('success', 'LC charge deleted.');
    }

    /**
     * Bank charges are the difference between the amount sent and the counterpart received.
     *
     * @param  array<string, mixed>  $data
     */
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
            'suppliers' => Contact::suppliers()->orderBy('name')->get(),
            'statuses' => Lc::statuses(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'supplier_id' => 'nullable|exists:contacts,id',
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
