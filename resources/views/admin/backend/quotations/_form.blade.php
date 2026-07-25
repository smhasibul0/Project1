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

{{-- ===================== Packing list (mandatory) ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Packing List <span class="text-danger">*</span></h6></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Customer's Packing List (Excel)</label>
                <input type="file" class="form-control" name="packing_list" id="packingListInput" accept=".xlsx,.xls">
                @error('packing_list')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($quotation?->packing_list_path)
                    <div class="small text-muted mt-1">
                        Current file: <a href="{{ asset('upload/quotation/'.$quotation->packing_list_path) }}" target="_blank">download</a>
                        — uploading a new one replaces it.
                    </div>
                @endif
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-primary" id="parsePackingBtn" disabled>
                    <i class="ri-file-excel-2-line me-1"></i> Read &amp; Fill Items
                </button>
            </div>
        </div>
        <div id="packingParseResult" class="mt-3 d-none"></div>
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

{{-- ===================== Predicted freight (LCL / FCL) ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Predicted Freight</h6></div>
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Shipment Type</label>
            <select class="form-control" name="freight_type" id="freightType">
                <option value="">— none —</option>
                <option value="lcl" @selected(old('freight_type', $quotation?->freight_type) === 'lcl')>LCL (shared, per CBM)</option>
                <option value="fcl" @selected(old('freight_type', $quotation?->freight_type) === 'fcl')>FCL (full container)</option>
            </select>
        </div>
        <div class="col-md-2 freight-lcl d-none">
            <label class="form-label">Rate per CBM</label>
            <input type="number" step="0.01" min="0" class="form-control" name="freight_rate" id="freightRate" value="{{ old('freight_rate', $quotation?->freight_rate) }}" placeholder="e.g. 55.00">
        </div>
        <div class="col-md-2 freight-lcl d-none">
            <label class="form-label text-muted">Total CBM (items)</label>
            <input type="text" class="form-control bg-light" id="freightCbm" readonly value="0.0000">
        </div>
        <div class="col-md-2 freight-fcl d-none">
            <label class="form-label">Container Size</label>
            <select class="form-control" name="freight_container_size">
                <option value="">--</option>
                @foreach(\App\Models\Container::containerSizes() as $size)
                    <option value="{{ $size }}" @selected(old('freight_container_size', $quotation?->freight_container_size) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 freight-fcl d-none">
            <label class="form-label">Container Price</label>
            <input type="number" step="0.01" min="0" class="form-control" name="freight_amount" id="freightFclAmount" value="{{ old('freight_amount', $quotation?->freight_type === 'fcl' ? $quotation?->freight_amount : null) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label text-muted">Predicted Freight</label>
            <input type="text" class="form-control bg-light" id="freightOut" readonly value="0.00">
        </div>
    </div>
</div>

{{-- ===================== Predicted LC costs ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Predicted LC Costs</h6>
        <button type="button" class="btn btn-sm btn-primary add-expense" data-group="lc"><i class="ri-add-line me-1"></i> Add LC Cost</button>
    </div>
    <div class="card-body">
        <div class="expense-wrap" data-group="lc"></div>
        <div class="text-end small text-muted">Subtotal: ৳ <span id="lcTotal">0.00</span></div>
    </div>
</div>

{{-- ===================== Custom predicted expenses ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Custom Expenses (predicted)</h6>
        <button type="button" class="btn btn-sm btn-primary add-expense" data-group="custom"><i class="ri-add-line me-1"></i> Add Expense</button>
    </div>
    <div class="card-body">
        <div class="expense-wrap" data-group="custom"></div>
        <div class="text-end small text-muted">Subtotal: ৳ <span id="customTotal">0.00</span></div>
    </div>
</div>

{{-- ===================== Cost projection summary ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Cost Projection Summary</h6></div>
    <div class="card-body row justify-content-end">
        <div class="col-md-6">
            <table class="table table-sm mb-0">
                <tr><th class="text-end">Goods Cost (supplier):</th><td class="text-end" style="width:35%">৳ <span id="projGoods">0.00</span></td></tr>
                <tr><th class="text-end">Duty &amp; Taxes (TTI):</th><td class="text-end">৳ <span id="projDuty">0.00</span></td></tr>
                <tr><th class="text-end">Predicted Freight:</th><td class="text-end">৳ <span id="projFreight">0.00</span></td></tr>
                <tr><th class="text-end">Predicted LC Costs:</th><td class="text-end">৳ <span id="projLc">0.00</span></td></tr>
                <tr><th class="text-end">Custom Expenses:</th><td class="text-end">৳ <span id="projCustom">0.00</span></td></tr>
                <tr class="table-light"><th class="text-end">Projected Total Cost:</th><td class="text-end">৳ <span id="projCost">0.00</span></td></tr>
                <tr><th class="text-end">Asking Total:</th><td class="text-end">৳ <span id="projAsking">0.00</span></td></tr>
                <tr class="fw-bold"><th class="text-end">Projected Net Profit:</th><td class="text-end">৳ <span id="projProfit">0.00</span> (<span id="projMargin">0.00</span>%)</td></tr>
            </table>
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
    <div class="mb-3 item-block">
        <div class="item-head">
            <div class="item-title">Product</div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove this product"><i class="ri-close-line"></i></button>
        </div>
        <div class="item-body">
        <input type="hidden" data-name="product_id">

        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">Description / Item Name</label>
                <input type="text" class="form-control form-control-sm item-description" data-name="description" placeholder="e.g. Ex7 Hand pump">
            </div>
            <div class="col-md-2">
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
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-2">
                <label class="form-label small">Nature of Packing</label>
                <select class="form-control form-control-sm" data-name="packing_type_id">
                    <option value="">--</option>
                    @foreach($packingTypes as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
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
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-2">
                <label class="form-label small">Height (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="height">
            </div>
            <div class="col-md-2">
                <label class="form-label small">CBM</label>
                <input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-name="cbm">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Supplier Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="supplier_asking_price" value="0">
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <label class="form-label small">Our Asking Price</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="our_asking_price" value="0">
            </div>
        </div>

        {{-- Duty & tax projection (Bangladesh Customs cascade) --}}
        <div class="duty-section">
        <div class="section-caption mb-2">Duty &amp; Tax Projection (BD Customs)</div>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label small">Assessable Value</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc av-field" data-name="assessable_value" placeholder="auto">
            </div>
            <div class="col-md-1">
                <label class="form-label small">CD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="cd_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">RD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="rd_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">SD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="sd_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">VAT %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="vat_rate" value="15">
            </div>
            <div class="col-md-1">
                <label class="form-label small">AIT %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="ait_rate" value="5">
            </div>
            <div class="col-md-1">
                <label class="form-label small">AT %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="at_rate" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Line Duty (TTI)</label>
                <input type="text" class="form-control form-control-sm bg-light out-duty" readonly value="0.00">
            </div>
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
    </div>
</template>

{{-- ===================== Expense row template (LC & custom) ===================== --}}
<template id="expenseTemplate">
    <div class="row g-2 mb-2 expense-row align-items-end">
        <input type="hidden" data-name="expense_group">
        <div class="col-md-3 expense-category">
            <label class="form-label small">Cost Category</label>
            <select class="form-control form-control-sm" data-name="cost_category_id">
                <option value="">--</option>
                @foreach($costCategories as $cc)<option value="{{ $cc->id }}">{{ $cc->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Title</label>
            <input type="text" class="form-control form-control-sm" data-name="title" placeholder="e.g. LC opening commission">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Amount</label>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm expense-amount" data-name="amount" value="0">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Note</label>
            <input type="text" class="form-control form-control-sm" data-name="note">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger remove-expense"><i class="ri-close-line"></i></button>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('itemsWrap');
    const tmpl = document.getElementById('itemTemplate');
    let index = 0;
    const money = n => (Number(n) || 0).toFixed(2);

    // Bangladesh Customs cascade: CD & RD on AV, SD compounds, VAT/AT compound further, AIT on AV.
    function recalcDuty(block) {
        const val = name => parseFloat(block.querySelector('[data-name="' + name + '"]')?.value) || 0;
        const avField = block.querySelector('.av-field');
        if (!avField.dataset.touched) {
            avField.value = money(val('supplier_asking_price') * val('package_quantity') * 1.01);
        }
        const av = parseFloat(avField.value) || 0;
        const cd = av * val('cd_rate') / 100;
        const rd = av * val('rd_rate') / 100;
        const sd = (av + cd + rd) * val('sd_rate') / 100;
        const vat = (av + cd + rd + sd) * val('vat_rate') / 100;
        const ait = av * val('ait_rate') / 100;
        const at = (av + cd + rd + sd) * val('at_rate') / 100;
        block.querySelector('.out-duty').value = money(cd + rd + sd + vat + ait + at);
    }

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
        recalcDuty(block);
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
        recalcProjection();
    }

    function expenseGroupTotal(group) {
        let sum = 0;
        document.querySelectorAll('.expense-wrap[data-group="' + group + '"] .expense-amount')
            .forEach(el => sum += parseFloat(el.value) || 0);
        return sum;
    }

    // Predicted freight: LCL = rate x total item CBM; FCL = flat container price.
    function freightAmount() {
        const type = document.getElementById('freightType').value;
        let cbm = 0;
        wrap.querySelectorAll('.item-block [data-name="cbm"]').forEach(el => cbm += parseFloat(el.value) || 0);
        document.getElementById('freightCbm').value = cbm.toFixed(4);

        if (type === 'lcl') {
            return (parseFloat(document.getElementById('freightRate').value) || 0) * cbm;
        }
        if (type === 'fcl') {
            return parseFloat(document.getElementById('freightFclAmount').value) || 0;
        }
        return 0;
    }

    function recalcProjection() {
        let goods = 0, duty = 0, asking = 0;
        wrap.querySelectorAll('.item-block').forEach(function (b) {
            const val = name => parseFloat(b.querySelector('[data-name="' + name + '"]')?.value) || 0;
            goods += val('supplier_asking_price') * val('package_quantity');
            duty += parseFloat(b.querySelector('.out-duty').value) || 0;
            asking += parseFloat(b.querySelector('.out-line-total').value) || 0;
        });
        const freight = freightAmount();
        const lc = expenseGroupTotal('lc'), custom = expenseGroupTotal('custom');
        const cost = goods + duty + freight + lc + custom;
        const profit = asking - cost;
        document.getElementById('freightOut').value = money(freight);
        document.getElementById('lcTotal').textContent = money(lc);
        document.getElementById('customTotal').textContent = money(custom);
        document.getElementById('projGoods').textContent = money(goods);
        document.getElementById('projDuty').textContent = money(duty);
        document.getElementById('projFreight').textContent = money(freight);
        document.getElementById('projLc').textContent = money(lc);
        document.getElementById('projCustom').textContent = money(custom);
        document.getElementById('projCost').textContent = money(cost);
        document.getElementById('projAsking').textContent = money(asking);
        document.getElementById('projProfit').textContent = money(profit);
        document.getElementById('projMargin').textContent = money(asking > 0 ? (profit / asking) * 100 : 0);
    }

    // ---------------- Freight controls ----------------
    const freightType = document.getElementById('freightType');

    function toggleFreightFields() {
        const type = freightType.value;
        document.querySelectorAll('.freight-lcl').forEach(el => el.classList.toggle('d-none', type !== 'lcl'));
        document.querySelectorAll('.freight-fcl').forEach(el => el.classList.toggle('d-none', type !== 'fcl'));
        recalcProjection();
    }

    freightType.addEventListener('change', toggleFreightFields);
    document.getElementById('freightRate').addEventListener('input', recalcProjection);
    document.getElementById('freightFclAmount').addEventListener('input', recalcProjection);

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

        // Keep a manually-overridden assessable value; otherwise it follows cost x qty + 1% landing.
        const avField = block.querySelector('.av-field');
        if (data && data.assessable_value != null && data.assessable_value !== '') {
            const def = (parseFloat(data.supplier_asking_price) || 0) * (parseFloat(data.package_quantity) || 0) * 1.01;
            if (Math.abs(parseFloat(data.assessable_value) - def) > 0.02) { avField.dataset.touched = '1'; }
        }
        avField.addEventListener('input', function () { this.dataset.touched = '1'; });

        block.querySelector('[data-name="description"]').addEventListener('input', renumber);
        block.querySelectorAll('.calc, .dim').forEach(el => el.addEventListener('input', () => recalcBlock(block)));
        block.querySelectorAll('.dim').forEach(el => el.addEventListener('input', () => autoCbm(block)));
        block.querySelector('[data-name="cbm"]').addEventListener('input', function () {
            this.dataset.touched = '1';
            recalcProjection(); // CBM feeds the LCL freight estimate
        });
        block.querySelector('.remove-item').addEventListener('click', function () {
            if (wrap.querySelectorAll('.item-block').length > 1) { block.remove(); renumber(); recalcGrand(); }
        });

        wrap.appendChild(node);
        renumber();
        recalcBlock(wrap.lastElementChild);
    }

    function renumber() {
        wrap.querySelectorAll('.item-block').forEach(function (b, n) {
            const desc = b.querySelector('[data-name="description"]')?.value || '';
            b.querySelector('.item-title').textContent = 'Product #' + (n + 1) + (desc ? ' — ' + desc : '');
        });
    }

    document.getElementById('addItemBtn').addEventListener('click', () => addItem());

    // ---------------- Predicted LC / custom expenses ----------------
    const expTmpl = document.getElementById('expenseTemplate');
    let expIndex = 0;

    function addExpense(group, data) {
        const node = expTmpl.content.cloneNode(true);
        const row = node.querySelector('.expense-row');

        row.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'expenses[' + expIndex + '][' + el.dataset.name + ']';
            if (data && data[el.dataset.name] !== undefined && data[el.dataset.name] !== null) {
                el.value = data[el.dataset.name];
            }
        });
        row.querySelector('[data-name="expense_group"]').value = group;
        if (group === 'custom') { row.querySelector('.expense-category').classList.add('d-none'); }

        row.querySelector('.expense-amount').addEventListener('input', recalcProjection);
        row.querySelector('.remove-expense').addEventListener('click', function () {
            row.remove();
            recalcProjection();
        });

        document.querySelector('.expense-wrap[data-group="' + group + '"]').appendChild(node);
        expIndex++;
        recalcProjection();
    }

    document.querySelectorAll('.add-expense').forEach(btn =>
        btn.addEventListener('click', () => addExpense(btn.dataset.group)));

    // ---------------- Packing list parse & fill ----------------
    const fileInput = document.getElementById('packingListInput');
    const parseBtn = document.getElementById('parsePackingBtn');
    const resultBox = document.getElementById('packingParseResult');

    fileInput.addEventListener('change', () => { parseBtn.disabled = !fileInput.files.length; });

    parseBtn.addEventListener('click', async function () {
        const fd = new FormData();
        fd.append('packing_list', fileInput.files[0]);
        fd.append('_token', document.querySelector('input[name="_token"]').value);
        parseBtn.disabled = true;
        parseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Reading...';

        try {
            const res = await fetch('{{ route('quotation.parse.packing') }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok) { throw new Error(data.message || 'Could not read the packing list.'); }
            fillFromPackingList(data);
        } catch (e) {
            resultBox.className = 'mt-3 alert alert-danger mb-0';
            resultBox.textContent = e.message;
        } finally {
            parseBtn.disabled = false;
            parseBtn.innerHTML = '<i class="ri-file-excel-2-line me-1"></i> Read & Fill Items';
        }
    });

    function fillFromPackingList(data) {
        const hasContent = Array.from(wrap.querySelectorAll('.item-block')).some(b =>
            b.querySelector('[data-name="description"]').value !== '' ||
            (parseFloat(b.querySelector('[data-name="package_quantity"]').value) || 0) > 0);
        if (hasContent && !confirm('Replace the current product rows with the packing list items?')) { return; }

        wrap.innerHTML = '';
        data.items.forEach(it => addItem({
            description: it.description,
            package_quantity: it.quantity,
            net_weight: it.net_weight,
            gross_weight: it.gross_weight,
            cbm: it.cbm,
            remarks: (it.cartons ? it.cartons + ' carton(s)' : '') + (it.remark ? ' — ' + it.remark : ''),
        }));

        const t = data.totals, st = data.sheet_totals;
        const matches = !st || ['quantity', 'gross_weight', 'net_weight', 'cbm']
            .every(k => Math.abs((t[k] || 0) - (st[k] || 0)) < 0.01);
        resultBox.className = 'mt-3 alert mb-0 ' + (matches ? 'alert-success' : 'alert-warning');
        resultBox.innerHTML =
            '<strong>' + data.items.length + ' item(s)</strong> imported from ' + t.cartons + ' carton(s) — ' +
            'Qty: ' + t.quantity + ', G.W: ' + t.gross_weight + ' kg, N.W: ' + t.net_weight + ' kg, CBM: ' + t.cbm +
            (data.customer ? '<br>Customer on sheet: ' + data.customer : '') +
            (data.supplier ? ' | Supplier: ' + data.supplier : '') +
            (matches ? '' : '<br><strong>Warning:</strong> imported totals do not match the sheet\'s TOTAL row — please verify.');
    }

    const existing = @json($quotation?->items ?? []);
    if (existing.length) { existing.forEach(addItem); } else { addItem(); }

    const existingExpenses = @json($quotation?->expenses ?? []);
    existingExpenses.forEach(e => addExpense(e.expense_group, e));
    toggleFreightFields();
});
</script>
