<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\CostCategory;
use App\Models\Order;
use App\Models\OrderCost;
use App\Models\OrderPayment;
use App\Models\PackingType;
use App\Models\PaymentAccount;
use App\Models\Quotation;
use App\Models\Transaction;
use App\Models\TransportationMode;
use App\Models\Warehouse;
use App\Support\DutyCalculator;
use App\Support\NumberToWords;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'containers'])->withCount('items')->latest()->get();
        $accounts = PaymentAccount::where('is_active', true)->orderBy('name')->get();

        return view('admin.backend.orders.orders', compact('orders', 'accounts'));
    }

    public function create()
    {
        return view('admin.backend.orders.create', array_merge($this->formData(), ['order' => null]));
    }

    /**
     * Create an order from a quotation (copying its items, rates and predicted costs),
     * then open it for editing. Any quotation can be converted — accepted is simply the
     * usual point — but only once, so a repeated submit can't duplicate the order.
     */
    public function fromQuotation($quotationId)
    {
        $quotation = Quotation::with(['customer', 'items', 'expenses'])->findOrFail($quotationId);

        if ($quotation->status === 'converted') {
            $existing = Order::where('quotation_id', $quotation->id)->latest('id')->first();

            return redirect()->route($existing ? 'order.show' : 'quotations.index', $existing?->id)
                ->with('error', $quotation->quotation_no.' has already been converted to an order.');
        }

        $order = DB::transaction(function () use ($quotation) {
            $order = Order::create([
                'order_date' => now()->toDateString(),
                'quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'transportation_mode_id' => $quotation->transportation_mode_id,
                'country_of_loading' => $quotation->country_of_loading,
                'shipping_mark' => $quotation->customer?->shipping_mark,
                'goods_status' => 'pending',
                'delivery_status' => 'pending',
                // The quoted rates carry over as the order's rates.
                'cost_rate_per_cbm' => $quotation->freight_type === 'lcl' ? $quotation->freight_rate : 0,
                'sell_rate_per_cbm' => $quotation->sell_rate_per_cbm,
                'added_by' => Auth::id(),
            ]);

            foreach ($quotation->items as $qi) {
                $order->items()->create([
                    'hs_code_id' => $qi->hs_code_id,
                    'hs_code' => $qi->hs_code,
                    'item_description' => $qi->description,
                    'quantity' => $qi->package_quantity,
                    'package_quantity' => $qi->package_quantity,
                    'net_weight' => $qi->net_weight,
                    'cbm' => $qi->cbm,
                    'declared_value' => $qi->declared_value,
                    'assessable_value' => $qi->assessable_value,
                    'cd_rate' => $qi->cd_rate,
                    'rd_rate' => $qi->rd_rate,
                    'sd_rate' => $qi->sd_rate,
                    'vat_rate' => $qi->vat_rate,
                    'ait_rate' => $qi->ait_rate,
                    'at_rate' => $qi->at_rate,
                    'duty_amount' => $qi->duty_amount,
                    'line_total' => $qi->line_total,
                ]);
            }

            // An FCL container price is a flat cost, so it can't be derived from a
            // rate per CBM — it carries over as a cost line instead.
            if ($quotation->freight_type === 'fcl' && (float) $quotation->freight_amount > 0) {
                $order->costs()->create([
                    'title' => 'FCL freight (projected'.($quotation->freight_container_size ? ', '.$quotation->freight_container_size : '').')',
                    'amount' => $quotation->freight_amount,
                    'cost_date' => now()->toDateString(),
                    'note' => 'Projected from quotation '.$quotation->quotation_no,
                    'added_by' => Auth::id(),
                ]);
            }

            // Seed the order's cost ledger with the quotation's predicted expenses so
            // they can be adjusted or replaced with actuals during the order phase.
            foreach ($quotation->expenses as $expense) {
                $order->costs()->create([
                    'cost_category_id' => $expense->cost_category_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'cost_date' => now()->toDateString(),
                    'note' => trim(($expense->note ? $expense->note.' — ' : '').'Projected from quotation '.$quotation->quotation_no),
                    'added_by' => Auth::id(),
                ]);
            }

            $quotation->update(['status' => 'converted']);
            $this->recompute($order);
            $order->logStatus($order->goods_status, 'Order created from quotation '.$quotation->quotation_no, Auth::id());

            return $order;
        });

        return redirect()->route('order.edit', $order->id)
            ->with('success', 'Order created from quotation — add the shipment details.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($data, $request) {
            $order = Order::create($this->headerData($data) + ['added_by' => Auth::id()]);
            $this->syncItems($order, $data['items']);
            $this->syncPayments($order, $request->input('payments', []));
            $this->recompute($order);
            $order->logStatus($order->goods_status, 'Order created', Auth::id());

            return $order;
        });

        return redirect()->route('orders.index')->with('success', 'Order created successfully.');
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'quotation', 'transportationMode', 'packingType', 'items.hsCodeRecord', 'costs.category', 'costs.paymentAccount', 'payments.paymentAccount', 'tracking.changedBy', 'lcs.payments', 'containers'])
            ->findOrFail($id);
        $costCategories = CostCategory::where('is_active', true)->orderBy('name')->get();
        $accounts = PaymentAccount::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('admin.backend.orders.show', compact('order', 'costCategories', 'accounts', 'warehouses'));
    }

    public function edit($id)
    {
        $order = Order::with(['items', 'payments'])->findOrFail($id);

        return view('admin.backend.orders.edit', array_merge($this->formData(), compact('order')));
    }

    /**
     * Printable carton label: the QR the customer sticks on every package.
     *
     * Scanning it opens the public tracking page — the timeline for the customer,
     * and the carton-counting panel for staff who are signed in.
     */
    public function label($id)
    {
        $order = Order::with('customer')->findOrFail($id);

        return view('admin.backend.orders.label', [
            'order' => $order,
            'company' => CompanySetting::current(),
            'trackUrl' => route('order.track', $order->track_token),
        ]);
    }

    /**
     * Printable customer invoice, rendered from the order + company settings.
     */
    public function invoice($id)
    {
        $order = Order::with(['customer', 'items', 'containers'])->findOrFail($id);
        $company = CompanySetting::current();
        $currency = $company->currency ?: 'BDT';
        $amountWords = NumberToWords::make((float) $order->total_amount, $currency === 'BDT' ? 'Taka' : $currency);

        return view('admin.backend.orders.invoice', compact('order', 'company', 'amountWords'));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $data = $this->validated($request);
        $before = $this->contents($order);

        DB::transaction(function () use ($order, $data, $request) {
            $order->update($this->headerData($data));

            // The form re-saves every item and payment; only a real difference is
            // logged (below), not every row as if it were new.
            ActivityLog::withoutRecording(function () use ($order, $data, $request) {
                $order->items()->delete();
                $order->payments()->delete();
                $this->syncItems($order, $data['items']);
                $this->syncPayments($order, $request->input('payments', []));
            });

            $this->recompute($order);
        });

        $order->logChangedContents($before, $this->contents($order));

        return redirect()->route('orders.index')->with('success', 'Order updated successfully.');
    }

    public function destroy($id)
    {
        Order::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Order deleted successfully.');
    }

    /**
     * Move an order's goods status and log it to the tracking timeline. Available to
     * limited staff (warehouse/port) via the orders.update-status permission.
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'goods_status' => 'required|in:'.implode(',', array_keys(Order::goodsStatuses())),
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'note' => 'nullable|string|max:1000',
        ]);

        $status = $data['goods_status'];
        $today = now()->toDateString();
        $updates = ['goods_status' => $status];

        // Auto-stamp the matching logistics date when a stage is reached.
        if ($status === 'at_port' && ! $order->port_arrival_date) {
            $updates['port_arrival_date'] = $today;
        }
        if ($status === 'at_bd_warehouse') {
            if (! $order->bd_warehouse_date) {
                $updates['bd_warehouse_date'] = $today;
            }
            // Which warehouse the goods land in (defaults to the designated one).
            $updates['warehouse_id'] = $data['warehouse_id'] ?? $order->warehouse_id;
        }
        if ($status === 'delivered') {
            $updates['delivery_status'] = 'delivered';
            if (! $order->delivered_date) {
                $updates['delivered_date'] = $today;
            }
        }

        $order->update($updates);

        // Receive the goods into the chosen warehouse's inventory (once).
        if ($status === 'at_bd_warehouse' && $order->warehouse_id) {
            $order->fresh('items')->receiveIntoWarehouse((int) $order->warehouse_id, Auth::id());
        }

        if ($status === 'delivered' && $order->goods_handover_date && $order->delivered_date) {
            $order->update([
                'total_delivery_days' => Carbon::parse($order->goods_handover_date)->diffInDays(Carbon::parse($order->delivered_date)),
            ]);
        }

        $order->logStatus($status, $data['note'] ?? null, Auth::id());

        return redirect()->back()->with('success', 'Status updated to '.$order->statusLabel().'.');
    }

    /**
     * Record a single payment against an order (the "Pay" quick action). When a payment
     * account is chosen, the amount is also deposited into that account's ledger.
     */
    public function storePayment(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'note' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        DB::transaction(function () use ($order, $data, $request) {
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/payments');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $payment = $order->payments()->create([
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'note' => $data['note'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            // Deposit into the selected account's ledger.
            if (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->increment('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'credit',
                    'source' => 'order_payment',
                    'amount' => $data['amount'],
                    'credit' => $data['amount'],
                    'debit' => 0,
                    'running_balance' => $account->fresh()->balance,
                    'description' => 'Payment for order '.$order->order_no,
                    'payment_method' => $data['method'] ?? null,
                    'reference' => $order->order_no,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => OrderPayment::class,
                    'transactionable_id' => $payment->id,
                    'added_by' => Auth::id(),
                    'created_at' => $data['payment_date'],
                ]);
            }

            $this->recompute($order);
        });

        return redirect()->back()->with('success', 'Payment of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerData(array $data): array
    {
        return [
            'order_date' => $data['order_date'] ?? null,
            'quotation_id' => $data['quotation_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'shipment_no' => $data['shipment_no'] ?? null,
            'shipping_mark' => $data['shipping_mark'] ?? null,
            'transportation_mode_id' => $data['transportation_mode_id'] ?? null,
            'country_of_loading' => $data['country_of_loading'] ?? null,
            'packing_type_id' => $data['packing_type_id'] ?? null,
            'goods_handover_date' => $data['goods_handover_date'] ?? null,
            'tentative_receive_date' => $data['tentative_receive_date'] ?? null,
            'port_arrival_date' => $data['port_arrival_date'] ?? null,
            'bd_warehouse_date' => $data['bd_warehouse_date'] ?? null,
            'goods_status' => $data['goods_status'] ?? 'pending',
            'delivery_status' => $data['delivery_status'] ?? 'pending',
            'delivered_date' => $data['delivered_date'] ?? null,
            'cost_rate_per_cbm' => $data['cost_rate_per_cbm'] ?? 0,
            'sell_rate_per_cbm' => $data['sell_rate_per_cbm'] ?? 0,
            'discount_type' => $data['discount_type'] ?? 'fixed',
            'discount_value' => $data['discount_value'] ?? 0,
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Order $order, array $items): void
    {
        $sellRate = (float) $order->sell_rate_per_cbm;

        foreach ($items as $row) {
            $cbm = (float) ($row['cbm'] ?? 0);
            $declaredValue = (float) ($row['declared_value'] ?? 0);

            // Duty is assessed on the declared invoice value plus the landing charge.
            $assessableValue = ($row['assessable_value'] ?? '') !== '' && $row['assessable_value'] !== null
                ? (float) $row['assessable_value']
                : DutyCalculator::defaultAssessableValue($declaredValue);
            $duty = DutyCalculator::calculate($assessableValue, $row);

            $order->items()->create([
                'hs_code_id' => $row['hs_code_id'] ?? null,
                'hs_code' => $row['hs_code'] ?? null,
                'item_description' => $row['item_description'] ?? null,
                'quantity' => (float) ($row['quantity'] ?? 0),
                'package_quantity' => $row['package_quantity'] ?? 0,
                'net_weight' => $row['net_weight'] ?? null,
                'cbm' => $cbm,
                'actual_weight' => $row['actual_weight'] ?? null,
                'declared_value' => $declaredValue,
                'assessable_value' => $assessableValue,
                'cd_rate' => (float) ($row['cd_rate'] ?? 0),
                'rd_rate' => (float) ($row['rd_rate'] ?? 0),
                'sd_rate' => (float) ($row['sd_rate'] ?? 0),
                'vat_rate' => (float) ($row['vat_rate'] ?? 0),
                'ait_rate' => (float) ($row['ait_rate'] ?? 0),
                'at_rate' => (float) ($row['at_rate'] ?? 0),
                'duty_amount' => $duty['total'],
                // Freight is charged by the cubic metre at the order's rate.
                'line_total' => round($sellRate * $cbm, 2),
            ]);
        }
    }

    /**
     * Record a cost against an order (the "Add Cost" action). When a payment account is
     * chosen, the amount is paid out of that account: it decrements the balance and writes
     * a debit into the Phase-0 ledger.
     */
    public function storeCost(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'cost_category_id' => 'nullable|exists:cost_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'cost_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'attachment' => 'nullable|file|mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png|max:4096',
        ]);

        DB::transaction(function () use ($order, $data, $request) {
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachment = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
                $dir = public_path('upload/costs');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file->move($dir, $attachment);
            }

            $cost = $order->costs()->create([
                'cost_category_id' => $data['cost_category_id'] ?? null,
                'title' => $data['title'],
                'amount' => $data['amount'],
                'cost_date' => $data['cost_date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'attachment' => $attachment,
                'added_by' => Auth::id(),
            ]);

            // Pay out of the selected account's ledger (money leaving the business).
            if (! empty($data['payment_account_id'])) {
                $account = PaymentAccount::findOrFail($data['payment_account_id']);
                $account->decrement('balance', (float) $data['amount']);
                $account->transactions()->create([
                    'type' => 'debit',
                    'source' => 'order_cost',
                    'amount' => $data['amount'],
                    'credit' => 0,
                    'debit' => $data['amount'],
                    'running_balance' => $account->fresh()->balance,
                    'description' => $data['title'].' for order '.$order->order_no,
                    'reference' => $order->order_no,
                    'note' => $data['note'] ?? null,
                    'transactionable_type' => OrderCost::class,
                    'transactionable_id' => $cost->id,
                    'added_by' => Auth::id(),
                    'created_at' => $cost->cost_date,
                ]);
            }

            $this->recompute($order);
        });

        return redirect()->back()->with('success', 'Cost of '.number_format((float) $data['amount'], 2).' recorded.');
    }

    /**
     * Delete a cost, reversing its ledger entry (crediting the account back) when it was
     * paid from a payment account.
     */
    public function destroyCost($id, $costId)
    {
        $order = Order::findOrFail($id);
        $cost = $order->costs()->findOrFail($costId);

        DB::transaction(function () use ($order, $cost) {
            if ($cost->payment_account_id) {
                $account = PaymentAccount::find($cost->payment_account_id);
                if ($account) {
                    $account->increment('balance', (float) $cost->amount);
                }
                Transaction::where('transactionable_type', OrderCost::class)
                    ->where('transactionable_id', $cost->id)
                    ->delete();
            }

            $cost->delete();
            $this->recompute($order);
        });

        return redirect()->back()->with('success', 'Cost deleted.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function syncPayments(Order $order, array $payments): void
    {
        foreach ($payments as $row) {
            if (empty($row['amount'])) {
                continue;
            }
            $order->payments()->create([
                'amount' => (float) $row['amount'],
                'payment_date' => $row['payment_date'] ?? null,
                'method' => $row['method'] ?? null,
                'note' => $row['note'] ?? null,
                'added_by' => Auth::id(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function contents(Order $order): array
    {
        return [
            'items' => Order::activityFingerprint($order->items()),
            'payments' => Order::activityFingerprint($order->payments()),
        ];
    }

    /**
     * Recompute all financial rollups. Delegates to the Order model, which is the single
     * source of the profit formula (also used by the LC and Container controllers).
     */
    private function recompute(Order $order): void
    {
        $order->recomputeFinancials();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'customers' => Contact::customers()->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'transportationModes' => TransportationMode::orderBy('name')->get(),
            'packingTypes' => PackingType::orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'order_date' => 'nullable|date',
            'quotation_id' => 'nullable|exists:quotations,id',
            'customer_id' => 'nullable|exists:contacts,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'shipment_no' => 'nullable|string|max:255',
            'shipping_mark' => 'nullable|string|max:255',
            'transportation_mode_id' => 'nullable|exists:transportation_modes,id',
            'country_of_loading' => 'nullable|string|max:255',
            'packing_type_id' => 'nullable|exists:packing_types,id',
            'goods_handover_date' => 'nullable|date',
            'tentative_receive_date' => 'nullable|date',
            'port_arrival_date' => 'nullable|date',
            'bd_warehouse_date' => 'nullable|date',
            'goods_status' => 'nullable|string|max:50',
            'delivery_status' => 'nullable|in:pending,delivered',
            'delivered_date' => 'nullable|date',
            'cost_rate_per_cbm' => 'nullable|numeric|min:0',
            'sell_rate_per_cbm' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.hs_code_id' => 'nullable|exists:hs_codes,id',
            'items.*.hs_code' => 'nullable|string|max:100',
            'items.*.item_description' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.package_quantity' => 'nullable|numeric|min:0',
            'items.*.net_weight' => 'nullable|numeric|min:0',
            'items.*.cbm' => 'nullable|numeric|min:0',
            'items.*.actual_weight' => 'nullable|numeric|min:0',
            'items.*.declared_value' => 'nullable|numeric|min:0',
            'items.*.assessable_value' => 'nullable|numeric|min:0',
            'items.*.cd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.rd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.sd_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.vat_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.ait_rate' => 'nullable|numeric|min:0|max:1000',
            'items.*.at_rate' => 'nullable|numeric|min:0|max:1000',
            'payments' => 'nullable|array',
            'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.payment_date' => 'nullable|date',
            'payments.*.method' => 'nullable|string|max:50',
            'payments.*.note' => 'nullable|string|max:255',
        ]);
    }
}
