<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PackingType;
use App\Models\PaymentAccount;
use App\Models\Quotation;
use App\Models\TransportationMode;
use App\Models\Unit;
use App\Support\NumberToWords;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer')->withCount('items')->latest()->get();
        $accounts = PaymentAccount::where('is_active', true)->orderBy('name')->get();

        return view('admin.backend.orders.orders', compact('orders', 'accounts'));
    }

    public function create()
    {
        return view('admin.backend.orders.create', array_merge($this->formData(), ['order' => null]));
    }

    /**
     * Create an order from an accepted quotation (copies its products), then open it for editing.
     */
    public function fromQuotation($quotationId)
    {
        $quotation = Quotation::with('items.category')->findOrFail($quotationId);

        $order = DB::transaction(function () use ($quotation) {
            $order = Order::create([
                'order_date' => now()->toDateString(),
                'quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'goods_status' => 'pending',
                'delivery_status' => 'pending',
                'added_by' => Auth::id(),
            ]);

            foreach ($quotation->items as $qi) {
                $order->items()->create([
                    'item_description' => $qi->category->name ?? null,
                    'category_id' => $qi->category_id,
                    'hs_code' => $qi->hs_code,
                    'quantity' => $qi->package_quantity,
                    'package_quantity' => $qi->package_quantity,
                    'net_weight' => $qi->net_weight,
                    'cbm' => $qi->cbm,
                    'supplier_asking_price' => $qi->supplier_asking_price,
                    'our_asking_price' => $qi->our_asking_price,
                    'line_total' => round((float) $qi->our_asking_price * (float) $qi->package_quantity, 2),
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
            $this->syncExpenses($order, $request->input('expenses', []));
            $this->syncPayments($order, $request->input('payments', []));
            $this->recompute($order);
            $order->logStatus($order->goods_status, 'Order created', Auth::id());

            return $order;
        });

        return redirect()->route('orders.index')->with('success', 'Order created successfully.');
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'quotation', 'transportationMode', 'packingType', 'items.category', 'items.unit', 'expenses', 'payments.paymentAccount', 'tracking.changedBy'])
            ->findOrFail($id);

        return view('admin.backend.orders.show', compact('order'));
    }

    public function edit($id)
    {
        $order = Order::with(['items', 'expenses', 'payments'])->findOrFail($id);

        return view('admin.backend.orders.edit', array_merge($this->formData(), compact('order')));
    }

    /**
     * Printable customer invoice, rendered from the order + company settings.
     */
    public function invoice($id)
    {
        $order = Order::with(['customer', 'items.unit'])->findOrFail($id);
        $company = CompanySetting::current();
        $amountWords = NumberToWords::make((float) $order->total_amount, $company->currency === 'BDT' ? 'Taka' : $company->currency);

        return view('admin.backend.orders.invoice', compact('order', 'company', 'amountWords'));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $data = $this->validated($request);

        DB::transaction(function () use ($order, $data, $request) {
            $order->update($this->headerData($data));
            $order->items()->delete();
            $order->expenses()->delete();
            $order->payments()->delete();
            $this->syncItems($order, $data['items']);
            $this->syncExpenses($order, $request->input('expenses', []));
            $this->syncPayments($order, $request->input('payments', []));
            $this->recompute($order);
        });

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
            'note' => 'nullable|string|max:1000',
        ]);

        $status = $data['goods_status'];
        $today = now()->toDateString();
        $updates = ['goods_status' => $status];

        // Auto-stamp the matching logistics date when a stage is reached.
        if ($status === 'at_port' && ! $order->port_arrival_date) {
            $updates['port_arrival_date'] = $today;
        }
        if ($status === 'at_bd_warehouse' && ! $order->bd_warehouse_date) {
            $updates['bd_warehouse_date'] = $today;
        }
        if ($status === 'delivered') {
            $updates['delivery_status'] = 'delivered';
            if (! $order->delivered_date) {
                $updates['delivered_date'] = $today;
            }
        }

        $order->update($updates);

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
        foreach ($items as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            $rate = (float) ($row['our_asking_price'] ?? 0);

            $order->items()->create([
                'item_description' => $row['item_description'] ?? null,
                'category_id' => $row['category_id'] ?? null,
                'hs_code' => $row['hs_code'] ?? null,
                'quantity' => $qty,
                'unit_id' => $row['unit_id'] ?? null,
                'package_quantity' => $row['package_quantity'] ?? 0,
                'net_weight' => $row['net_weight'] ?? null,
                'cbm' => $row['cbm'] ?? null,
                'actual_weight' => $row['actual_weight'] ?? null,
                'supplier_asking_price' => $row['supplier_asking_price'] ?? 0,
                'our_asking_price' => $rate,
                'line_total' => round($rate * $qty, 2),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $expenses
     */
    private function syncExpenses(Order $order, array $expenses): void
    {
        foreach ($expenses as $row) {
            if (empty($row['title']) && empty($row['amount'])) {
                continue;
            }
            $order->expenses()->create([
                'title' => $row['title'] ?? 'Expense',
                'amount' => (float) ($row['amount'] ?? 0),
            ]);
        }
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
     * Recompute all financial rollups + delivery days from the saved items/expenses.
     */
    private function recompute(Order $order): void
    {
        $order->load('items', 'expenses', 'payments');

        $subtotal = round((float) $order->items->sum('line_total'), 2);
        $supplierCost = round((float) $order->items->sum(fn ($i) => (float) $i->supplier_asking_price * (float) $i->quantity), 2);
        $totalExpense = round((float) $order->expenses->sum('amount'), 2);

        $discount = $order->discount_type === 'percentage'
            ? round($subtotal * (float) $order->discount_value / 100, 2)
            : round((float) $order->discount_value, 2);

        $totalAmount = round($subtotal - $discount, 2);

        // Received rolls up from the individual payments.
        $received = round((float) $order->payments->sum('amount'), 2);
        $due = round($totalAmount - $received, 2);
        $lastPaymentDate = $order->payments->max('payment_date');

        $paymentStatus = $received <= 0 ? 'due' : ($received >= $totalAmount ? 'paid' : 'partial');

        $days = ($order->goods_handover_date && $order->delivered_date)
            ? Carbon::parse($order->goods_handover_date)->diffInDays(Carbon::parse($order->delivered_date))
            : null;

        $order->update([
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'total_expense' => $totalExpense,
            'received_amount' => $received,
            'amount_received_date' => $lastPaymentDate,
            'due_amount' => $due,
            'payment_status' => $paymentStatus,
            'profit' => round($totalAmount - $supplierCost - $totalExpense, 2),
            'total_delivery_days' => $days,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'customers' => Contact::customers()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
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
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_description' => 'nullable|string|max:255',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.hs_code' => 'nullable|string|max:100',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.package_quantity' => 'nullable|numeric|min:0',
            'items.*.net_weight' => 'nullable|numeric|min:0',
            'items.*.cbm' => 'nullable|numeric|min:0',
            'items.*.actual_weight' => 'nullable|numeric|min:0',
            'items.*.supplier_asking_price' => 'nullable|numeric|min:0',
            'items.*.our_asking_price' => 'nullable|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.title' => 'nullable|string|max:255',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.payment_date' => 'nullable|date',
            'payments.*.method' => 'nullable|string|max:50',
            'payments.*.note' => 'nullable|string|max:255',
        ]);
    }
}
