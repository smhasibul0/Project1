@php $quotation = $quotation ?? null; @endphp

{{-- ===================== Quotation header ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Quotation Details</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Query Received Date</label>
            <input type="date" class="form-control" name="query_received_date"
                   value="{{ old('query_received_date', optional($quotation?->query_received_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-5">
            <label class="form-label">Customer</label>
            <x-customer-select :customers="$customers" :selected="old('customer_id', $quotation?->customer_id)" />
        </div>
        @if($quotation)
        <div class="col-md-3">
            <label class="form-label d-block">Current Status</label>
            <span class="badge bg-info text-capitalize fs-6">{{ $quotation->statusLabel() }}</span>
        </div>
        @endif
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

        <div class="row justify-content-end mt-2">
            <div class="col-md-5">
                <table class="table table-sm mb-0">
                    <tr><th class="text-end">Grand Total:</th><td class="text-end" style="width:40%">৳ <span id="grandTotal">0.00</span></td></tr>
                    <tr><th class="text-end">Total Profit:</th><td class="text-end">৳ <span id="grandProfit">0.00</span></td></tr>
                    <tr><th class="text-end">Profit Margin:</th><td class="text-end"><span id="grandMargin">0.00</span>%</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <label class="form-label">Remarks</label>
        <textarea class="form-control" name="remarks" rows="2">{{ old('remarks', $quotation?->remarks) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <button type="submit" name="action" value="send" class="btn btn-primary px-4"><i class="ri-send-plane-line me-1"></i> Send to Customer</button>
    <button type="submit" name="action" value="draft" class="btn btn-outline-primary px-4">Save as Draft</button>
    <a href="{{ route('quotations.index') }}" class="btn btn-secondary">Cancel</a>
</div>

{{-- ===================== Product block template ===================== --}}
<template id="itemTemplate">
    <div class="border rounded p-3 mb-3 item-block position-relative">
        <button type="button" class="btn btn-sm btn-outline-danger remove-item position-absolute" style="top:.5rem; right:.5rem;"><i class="ri-close-line"></i></button>
        <input type="hidden" data-name="product_id">
        <div class="fw-semibold text-muted small mb-2 item-title">Product</div>

        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">Category</label>
                <select class="form-control form-control-sm" data-name="category_id">
                    <option value="">--</option>
                    @foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">HS Code</label>
                <input type="text" class="form-control form-control-sm" data-name="hs_code" placeholder="9503009">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Transport Mode</label>
                <select class="form-control form-control-sm" data-name="transportation_mode_id">
                    <option value="">--</option>
                    @foreach($transportationModes as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Country of Loading</label>
                <input type="text" class="form-control form-control-sm" data-name="country_of_loading" placeholder="China">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Nature of Packing</label>
                <select class="form-control form-control-sm" data-name="packing_type_id">
                    <option value="">--</option>
                    @foreach($packingTypes as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-2">
                <label class="form-label small">Package Qty</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="package_quantity" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Net Weight</label>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="net_weight">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Gross Weight</label>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="gross_weight">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Length (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="length">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Width (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="width">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Height (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="height">
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-2">
                <label class="form-label small">CBM</label>
                <input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-name="cbm">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Supplier Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="supplier_asking_price" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Providing Supplier</label>
                <select class="form-control form-control-sm" data-name="supplier_id">
                    <option value="">--</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->business_name ?: $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Supplier Quote Date</label>
                <input type="date" class="form-control form-control-sm" data-name="supplier_quotation_date">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Our Asking Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="our_asking_price" value="0">
            </div>
        </div>

        <div class="row g-2 mt-1 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted">Line Total</label>
                <input type="text" class="form-control form-control-sm bg-light out-line-total" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Unit Profit</label>
                <input type="text" class="form-control form-control-sm bg-light out-unit-profit" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Total Profit</label>
                <input type="text" class="form-control form-control-sm bg-light out-total-profit" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Margin %</label>
                <input type="text" class="form-control form-control-sm bg-light out-margin" readonly value="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Remarks</label>
                <input type="text" class="form-control form-control-sm" data-name="remarks">
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('itemsWrap');
    const tmpl = document.getElementById('itemTemplate');
    let index = 0;
    const money = n => (Number(n) || 0).toFixed(2);

    function recalcBlock(block) {
        const val = name => parseFloat(block.querySelector('[data-name="' + name + '"]')?.value) || 0;
        const qty = val('package_quantity'), cost = val('supplier_asking_price'), sell = val('our_asking_price');
        const lineTotal = sell * qty;
        const unitProfit = sell - cost;
        const totalProfit = unitProfit * qty;
        const margin = sell > 0 ? (unitProfit / sell) * 100 : 0;
        block.querySelector('.out-line-total').value = money(lineTotal);
        block.querySelector('.out-unit-profit').value = money(unitProfit);
        block.querySelector('.out-total-profit').value = money(totalProfit);
        block.querySelector('.out-margin').value = money(margin);
        recalcGrand();
    }

    function recalcGrand() {
        let gt = 0, gp = 0;
        wrap.querySelectorAll('.item-block').forEach(function (b) {
            gt += parseFloat(b.querySelector('.out-line-total').value) || 0;
            gp += parseFloat(b.querySelector('.out-total-profit').value) || 0;
        });
        document.getElementById('grandTotal').textContent = money(gt);
        document.getElementById('grandProfit').textContent = money(gp);
        document.getElementById('grandMargin').textContent = money(gt > 0 ? (gp / gt) * 100 : 0);
    }

    function autoCbm(block) {
        const l = parseFloat(block.querySelector('[data-name="length"]').value) || 0;
        const w = parseFloat(block.querySelector('[data-name="width"]').value) || 0;
        const h = parseFloat(block.querySelector('[data-name="height"]').value) || 0;
        const cbmField = block.querySelector('[data-name="cbm"]');
        if (l && w && h && !cbmField.dataset.touched) {
            cbmField.value = ((l * w * h) / 1000000).toFixed(4);
        }
    }

    function addItem(data) {
        const i = index++;
        const node = tmpl.content.cloneNode(true);
        const block = node.querySelector('.item-block');

        block.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'items[' + i + '][' + el.dataset.name + ']';
            if (data && data[el.dataset.name] !== undefined && data[el.dataset.name] !== null) {
                el.value = data[el.dataset.name];
            }
        });

        block.querySelectorAll('.calc, .dim').forEach(el => el.addEventListener('input', () => recalcBlock(block)));
        block.querySelectorAll('.dim').forEach(el => el.addEventListener('input', () => autoCbm(block)));
        block.querySelector('[data-name="cbm"]').addEventListener('input', function () { this.dataset.touched = '1'; });
        block.querySelector('.remove-item').addEventListener('click', function () {
            if (wrap.querySelectorAll('.item-block').length > 1) { block.remove(); renumber(); recalcGrand(); }
        });

        wrap.appendChild(node);
        renumber();
        recalcBlock(wrap.lastElementChild);
    }

    function renumber() {
        wrap.querySelectorAll('.item-block .item-title').forEach((t, n) => t.textContent = 'Product #' + (n + 1));
    }

    document.getElementById('addItemBtn').addEventListener('click', () => addItem());

    const existing = @json($quotation?->items ?? []);
    if (existing.length) { existing.forEach(addItem); } else { addItem(); }
});
</script>
