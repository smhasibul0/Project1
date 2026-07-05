@php
    $order = $order ?? null;
    $goodsStatuses = [
        'pending' => 'Pending', 'sourcing' => 'Sourcing', 'at_china_warehouse' => 'At China Warehouse',
        'shipped' => 'Shipped', 'at_port' => 'At Port', 'at_bd_warehouse' => 'At BD Warehouse',
        'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
@endphp

@if($order?->quotation_id)
    <input type="hidden" name="quotation_id" value="{{ $order->quotation_id }}">
@endif

{{-- ===================== Order info ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Order Details</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Order Date</label>
            <input type="date" class="form-control" name="order_date" value="{{ old('order_date', optional($order?->order_date)->format('Y-m-d') ?? now()->toDateString()) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Customer</label>
            <select class="form-control" name="customer_id">
                <option value="">-- Select Customer --</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" @selected(old('customer_id', $order?->customer_id) == $c->id)>{{ $c->name }} @if($c->business_name)({{ $c->business_name }})@endif</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Quotation</label>
            <input type="text" class="form-control" value="{{ $order?->quotation?->quotation_no ?? '—' }}" readonly>
        </div>
        <div class="col-md-2">
            <label class="form-label">Goods Status</label>
            <select class="form-control" name="goods_status">
                @foreach($goodsStatuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('goods_status', $order?->goods_status ?? 'pending') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- ===================== Shipment / logistics ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Shipment &amp; Logistics</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Shipment No</label>
            <input type="text" class="form-control" name="shipment_no" value="{{ old('shipment_no', $order?->shipment_no) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Shipping Mark</label>
            <input type="text" class="form-control" name="shipping_mark" value="{{ old('shipping_mark', $order?->shipping_mark) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Transport Mode</label>
            <select class="form-control" name="transportation_mode_id">
                <option value="">--</option>
                @foreach($transportationModes as $m)<option value="{{ $m->id }}" @selected(old('transportation_mode_id', $order?->transportation_mode_id) == $m->id)>{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Country of Loading</label>
            <input type="text" class="form-control" name="country_of_loading" value="{{ old('country_of_loading', $order?->country_of_loading) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Nature of Packing</label>
            <select class="form-control" name="packing_type_id">
                <option value="">--</option>
                @foreach($packingTypes as $p)<option value="{{ $p->id }}" @selected(old('packing_type_id', $order?->packing_type_id) == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Goods Handover Date</label>
            <input type="date" class="form-control" name="goods_handover_date" value="{{ old('goods_handover_date', optional($order?->goods_handover_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Tentative Receive Date</label>
            <input type="date" class="form-control" name="tentative_receive_date" value="{{ old('tentative_receive_date', optional($order?->tentative_receive_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Port Arrival Date</label>
            <input type="date" class="form-control" name="port_arrival_date" value="{{ old('port_arrival_date', optional($order?->port_arrival_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Reached BD Warehouse</label>
            <input type="date" class="form-control" name="bd_warehouse_date" value="{{ old('bd_warehouse_date', optional($order?->bd_warehouse_date)->format('Y-m-d')) }}">
        </div>
    </div>
</div>

{{-- ===================== Products ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Products</h6>
        <button type="button" class="btn btn-sm btn-primary" id="addItemBtn"><i class="ri-add-line me-1"></i> Add Product</button>
    </div>
    <div class="card-body">
        <div id="itemsWrap"></div>
    </div>
</div>

{{-- ===================== Expenses ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Additional Expenses</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addExpenseBtn"><i class="ri-add-line me-1"></i> Add Expense</button>
    </div>
    <div class="card-body">
        <div id="expensesWrap"></div>
    </div>
</div>

{{-- ===================== Financials + status ===================== --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payment &amp; Delivery</h6></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Discount Type</label>
                    <select class="form-control" name="discount_type" id="discountType">
                        <option value="fixed" @selected(old('discount_type', $order?->discount_type ?? 'fixed') === 'fixed')>Fixed (৳)</option>
                        <option value="percentage" @selected(old('discount_type', $order?->discount_type) === 'percentage')>Percentage (%)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Discount Value</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="discount_value" id="discountValue" value="{{ old('discount_value', $order?->discount_value ?? 0) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Received Amount</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="received_amount" id="receivedAmount" value="{{ old('received_amount', $order?->received_amount ?? 0) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount Received Date</label>
                    <input type="date" class="form-control" name="amount_received_date" value="{{ old('amount_received_date', optional($order?->amount_received_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Delivery Status</label>
                    <select class="form-control" name="delivery_status">
                        <option value="pending" @selected(old('delivery_status', $order?->delivery_status ?? 'pending') === 'pending')>Pending</option>
                        <option value="delivered" @selected(old('delivery_status', $order?->delivery_status) === 'delivered')>Delivered</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Delivered Date</label>
                    <input type="date" class="form-control" name="delivered_date" value="{{ old('delivered_date', optional($order?->delivered_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="2">{{ old('remarks', $order?->remarks) }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Summary</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal (receivable)</th><td class="text-end">৳ <span id="sumSubtotal">0.00</span></td></tr>
                    <tr><th>Discount</th><td class="text-end">− ৳ <span id="sumDiscount">0.00</span></td></tr>
                    <tr><th>Total Amount</th><td class="text-end fw-semibold">৳ <span id="sumTotal">0.00</span></td></tr>
                    <tr><th>Received</th><td class="text-end">৳ <span id="sumReceived">0.00</span></td></tr>
                    <tr><th>Due</th><td class="text-end">৳ <span id="sumDue">0.00</span></td></tr>
                    <tr><th>Supplier Cost</th><td class="text-end">৳ <span id="sumCost">0.00</span></td></tr>
                    <tr><th>Expenses</th><td class="text-end">৳ <span id="sumExpense">0.00</span></td></tr>
                    <tr class="table-light"><th>Profit</th><td class="text-end fw-semibold">৳ <span id="sumProfit">0.00</span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 my-4">
    <button type="submit" class="btn btn-primary px-4">{{ $order ? 'Update Order' : 'Save Order' }}</button>
    <a href="{{ route('orders.index') }}" class="btn btn-secondary">Cancel</a>
</div>

{{-- ===================== Templates ===================== --}}
<template id="itemTemplate">
    <div class="border rounded p-3 mb-3 item-block position-relative">
        <button type="button" class="btn btn-sm btn-outline-danger remove-item position-absolute" style="top:.5rem; right:.5rem;"><i class="ri-close-line"></i></button>
        <div class="fw-semibold text-muted small mb-2 item-title">Product</div>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small">Item Description (Nature of Goods)</label><input type="text" class="form-control form-control-sm" data-name="item_description"></div>
            <div class="col-md-3"><label class="form-label small">Category</label><select class="form-control form-control-sm" data-name="category_id"><option value="">--</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">HS Code</label><input type="text" class="form-control form-control-sm" data-name="hs_code"></div>
            <div class="col-md-3"><label class="form-label small">Nature of Packing (pkg)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-name="package_quantity" value="0"></div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-2"><label class="form-label small">Quantity</label><input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="quantity" value="0"></div>
            <div class="col-md-2"><label class="form-label small">Unit</label><select class="form-control form-control-sm" data-name="unit_id"><option value="">--</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">Net Weight (kg)</label><input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="net_weight"></div>
            <div class="col-md-2"><label class="form-label small">Volume (CBM)</label><input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-name="cbm"></div>
            <div class="col-md-2"><label class="form-label small">Actual Wt (BD)</label><input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="actual_weight"></div>
        </div>
        <div class="row g-2 mt-1 align-items-end">
            <div class="col-md-3"><label class="form-label small">Supplier Price</label><input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="supplier_asking_price" value="0"></div>
            <div class="col-md-3"><label class="form-label small">Our Price to Client</label><input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="our_asking_price" value="0"></div>
            <div class="col-md-3"><label class="form-label small text-muted">Line Total</label><input type="text" class="form-control form-control-sm bg-light out-line-total" readonly value="0.00"></div>
        </div>
    </div>
</template>

<template id="expenseTemplate">
    <div class="row g-2 mb-2 expense-row align-items-end">
        <div class="col-md-6"><input type="text" class="form-control form-control-sm" data-name="title" placeholder="Expense title (e.g. Freight, Customs)"></div>
        <div class="col-md-4"><input type="number" step="0.01" min="0" class="form-control form-control-sm calc-exp" data-name="amount" placeholder="Amount"></div>
        <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger remove-expense w-100"><i class="ri-close-line"></i></button></div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsWrap = document.getElementById('itemsWrap');
    const expWrap = document.getElementById('expensesWrap');
    const itemTmpl = document.getElementById('itemTemplate');
    const expTmpl = document.getElementById('expenseTemplate');
    let ii = 0, ei = 0;
    const money = n => (Number(n) || 0).toFixed(2);
    const num = el => parseFloat(el?.value) || 0;

    function recalc() {
        let subtotal = 0, cost = 0, expenses = 0;
        itemsWrap.querySelectorAll('.item-block').forEach(function (b) {
            const qty = num(b.querySelector('[data-name="quantity"]'));
            const sup = num(b.querySelector('[data-name="supplier_asking_price"]'));
            const our = num(b.querySelector('[data-name="our_asking_price"]'));
            const lt = our * qty;
            b.querySelector('.out-line-total').value = money(lt);
            subtotal += lt;
            cost += sup * qty;
        });
        expWrap.querySelectorAll('.expense-row').forEach(r => expenses += num(r.querySelector('[data-name="amount"]')));

        const dType = document.getElementById('discountType').value;
        const dVal = num(document.getElementById('discountValue'));
        const discount = dType === 'percentage' ? subtotal * dVal / 100 : dVal;
        const total = subtotal - discount;
        const received = num(document.getElementById('receivedAmount'));

        document.getElementById('sumSubtotal').textContent = money(subtotal);
        document.getElementById('sumDiscount').textContent = money(discount);
        document.getElementById('sumTotal').textContent = money(total);
        document.getElementById('sumReceived').textContent = money(received);
        document.getElementById('sumDue').textContent = money(total - received);
        document.getElementById('sumCost').textContent = money(cost);
        document.getElementById('sumExpense').textContent = money(expenses);
        document.getElementById('sumProfit').textContent = money(total - cost - expenses);
    }

    function addItem(data) {
        const i = ii++;
        const node = itemTmpl.content.cloneNode(true);
        const block = node.querySelector('.item-block');
        block.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'items[' + i + '][' + el.dataset.name + ']';
            if (data && data[el.dataset.name] != null) el.value = data[el.dataset.name];
        });
        block.querySelectorAll('.calc').forEach(el => el.addEventListener('input', recalc));
        block.querySelector('.remove-item').addEventListener('click', function () {
            if (itemsWrap.querySelectorAll('.item-block').length > 1) { block.remove(); renumber(); recalc(); }
        });
        itemsWrap.appendChild(node);
        renumber();
        recalc();
    }

    function addExpense(data) {
        const i = ei++;
        const node = expTmpl.content.cloneNode(true);
        const row = node.querySelector('.expense-row');
        row.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'expenses[' + i + '][' + el.dataset.name + ']';
            if (data && data[el.dataset.name] != null) el.value = data[el.dataset.name];
        });
        row.querySelector('.calc-exp').addEventListener('input', recalc);
        row.querySelector('.remove-expense').addEventListener('click', function () { row.remove(); recalc(); });
        expWrap.appendChild(node);
        recalc();
    }

    function renumber() {
        itemsWrap.querySelectorAll('.item-block .item-title').forEach((t, n) => t.textContent = 'Product #' + (n + 1));
    }

    document.getElementById('addItemBtn').addEventListener('click', () => addItem());
    document.getElementById('addExpenseBtn').addEventListener('click', () => addExpense());
    ['discountType', 'discountValue', 'receivedAmount'].forEach(id => document.getElementById(id).addEventListener('input', recalc));
    document.getElementById('discountType').addEventListener('change', recalc);

    const items = @json($order?->items ?? []);
    const expenses = @json($order?->expenses ?? []);
    if (items.length) { items.forEach(addItem); } else { addItem(); }
    expenses.forEach(addExpense);
    recalc();
});
</script>
