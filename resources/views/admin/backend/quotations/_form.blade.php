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

        {{-- One shipment moves one way from one place, so these sit on the quotation. --}}
        <div class="col-md-3">
            <label class="form-label">Transport Mode</label>
            <select class="form-control" name="transportation_mode_id">
                <option value="">--</option>
                @foreach($transportationModes as $m)
                    <option value="{{ $m->id }}" @selected(old('transportation_mode_id', $quotation?->transportation_mode_id) == $m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Country of Loading</label>
            <input type="text" class="form-control" name="country_of_loading"
                   value="{{ old('country_of_loading', $quotation?->country_of_loading) }}" placeholder="China">
        </div>
    </div>
</div>

{{-- ===================== Packing list (mandatory) ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Packing List <span class="text-muted fw-normal small">— optional</span></h6></div>
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
            {{-- Auto-filling the item rows from the sheet is part of building a quote. --}}
            @can('quotations.create')
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-primary" id="parsePackingBtn" disabled>
                    <i class="ri-file-excel-2-line me-1"></i> Read &amp; Fill Items
                </button>
            </div>
            @endcan
        </div>
        <div id="packingParseResult" class="mt-3 d-none"></div>
    </div>
</div>

{{-- ===================== Shipment items ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Shipment Items <span class="text-muted fw-normal small">— search an HS code to fill the description &amp; tax rates</span></h6>
        <button type="button" class="btn btn-sm btn-primary" id="addItemBtn"><i class="ri-add-line me-1"></i> Add Item</button>
    </div>
    <div class="card-body">
        <div id="itemsWrap"></div>

        <div class="row justify-content-end mt-2">
            <div class="col-md-5">
                <table class="table table-sm mb-0">
                    <tr><th class="text-end">Total CBM:</th><td class="text-end" style="width:40%"><span id="itemsCbm">0.0000</span></td></tr>
                    <tr><th class="text-end">Declared Goods Value:</th><td class="text-end">৳ <span id="itemsDeclared">0.00</span></td></tr>
                    <tr><th class="text-end">Duty &amp; Taxes:</th><td class="text-end">৳ <span id="itemsDuty">0.00</span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Cost & charge per CBM ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Cost &amp; Charge per CBM</h6></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Shipment Type</label>
                <select class="form-control" name="freight_type" id="freightType">
                    <option value="">— none —</option>
                    <option value="lcl" @selected(old('freight_type', $quotation?->freight_type) === 'lcl')>LCL (shared, per CBM)</option>
                    <option value="fcl" @selected(old('freight_type', $quotation?->freight_type) === 'fcl')>FCL (full container)</option>
                </select>
            </div>
            <div class="col-md-3 freight-lcl d-none">
                <label class="form-label">Our Cost per CBM</label>
                <input type="number" step="0.01" min="0" class="form-control" name="freight_rate" id="freightRate"
                       value="{{ old('freight_rate', $quotation?->freight_rate) }}" placeholder="e.g. 55.00">
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
            <div class="col-md-3 freight-fcl d-none">
                <label class="form-label">Container Price (total)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="freight_amount" id="freightFclAmount"
                       value="{{ old('freight_amount', $quotation?->freight_type === 'fcl' ? $quotation?->freight_amount : null) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rate We Charge per CBM <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control" name="sell_rate_per_cbm" id="sellRate"
                       value="{{ old('sell_rate_per_cbm', $quotation?->sell_rate_per_cbm) }}" placeholder="e.g. 85.00">
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label class="form-label text-muted">Total CBM (from items)</label>
                <input type="text" class="form-control bg-light" id="freightCbm" readonly value="0.0000">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Freight Cost</label>
                <input type="text" class="form-control bg-light" id="freightOut" readonly value="0.00">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Charge to Customer</label>
                <input type="text" class="form-control bg-light fw-semibold" id="chargeOut" readonly value="0.00">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Margin per CBM</label>
                <input type="text" class="form-control bg-light" id="marginPerCbm" readonly value="0.00">
            </div>
        </div>

        <div class="small text-muted mt-2">
            The rate charged covers everything — freight, duty and clearing. Duty is a cost to us, not a separate line on the customer's bill.
        </div>
    </div>
</div>

{{-- ===================== Additional predicted costs ===================== --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Additional Costs <span class="text-muted fw-normal small">— anything else this shipment will cost us</span></h6>
        <button type="button" class="btn btn-sm btn-primary" id="addExpenseBtn"><i class="ri-add-line me-1"></i> Add Cost</button>
    </div>
    <div class="card-body">
        <div id="expensesWrap"></div>
        <div class="text-end small text-muted">Subtotal: ৳ <span id="customTotal">0.00</span></div>
    </div>
</div>

{{-- ===================== What it costs vs what we ask ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Total Cost vs. Customer Price</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <table class="table table-sm mb-0">
                <thead><tr><th colspan="2" class="text-uppercase small text-muted">What this shipment costs us</th></tr></thead>
                <tr><th class="text-end">Freight:</th><td class="text-end" style="width:40%">৳ <span id="projFreight">0.00</span></td></tr>
                <tr><th class="text-end">Duty &amp; Taxes (TTI):</th><td class="text-end">৳ <span id="projDuty">0.00</span></td></tr>
                <tr><th class="text-end">Additional Costs:</th><td class="text-end">৳ <span id="projCustom">0.00</span></td></tr>
                <tr class="table-light fw-bold"><th class="text-end">Total Cost:</th><td class="text-end">৳ <span id="projCost">0.00</span></td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-0">
                <thead><tr><th colspan="2" class="text-uppercase small text-muted">What we ask the customer</th></tr></thead>
                <tr><th class="text-end">Rate per CBM:</th><td class="text-end" style="width:40%">৳ <span id="askRate">0.00</span></td></tr>
                <tr><th class="text-end">Total CBM:</th><td class="text-end"><span id="askCbm">0.0000</span></td></tr>
                <tr class="table-light fw-bold"><th class="text-end">Customer Price:</th><td class="text-end">৳ <span id="projAsking">0.00</span></td></tr>
                <tr class="fw-bold text-success"><th class="text-end">Projected Profit:</th><td class="text-end">৳ <span id="projProfit">0.00</span></td></tr>
                <tr><th class="text-end">Margin:</th><td class="text-end"><span id="projMargin">0.00</span>%</td></tr>
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

<x-hs-search />

{{-- ===================== Item block template ===================== --}}
<template id="itemTemplate">
    <div class="mb-3 item-block">
        <div class="item-head">
            <div class="item-title">Item</div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove this item"><i class="ri-close-line"></i></button>
        </div>
        <div class="item-body">
        <input type="hidden" data-name="hs_code_id">

        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">HS Code <i class="ri-search-line"></i></label>
                <div class="hs-search-wrap">
                    <input type="text" class="form-control form-control-sm hs-search" data-name="hs_code"
                           placeholder="3911.90.00 or a keyword" autocomplete="off">
                    <div class="hs-results"></div>
                </div>
            </div>
            <div class="col-md-7">
                <label class="form-label small">Description</label>
                <input type="text" class="form-control form-control-sm item-description" data-name="description" placeholder="filled from the HS code">
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
            <div class="col-md-3">
                <label class="form-label small">Package Qty</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="package_quantity" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Net Weight</label>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="net_weight">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Gross Weight</label>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="gross_weight">
            </div>
            <div class="col-md-1">
                <label class="form-label small">L (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="length">
            </div>
            <div class="col-md-1">
                <label class="form-label small">W (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="width">
            </div>
            <div class="col-md-1">
                <label class="form-label small">H (cm)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm dim" data-name="height">
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold">CBM</label>
                <input type="number" step="0.0001" min="0" class="form-control form-control-sm calc" data-name="cbm">
            </div>
        </div>

        {{-- Duty & tax projection (Bangladesh Customs cascade) --}}
        <div class="duty-section">
        <div class="section-caption mb-2">Duty &amp; Tax on the Declared Value (BD Customs)</div>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label small">Declared Value</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="declared_value" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Assessable Value</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc av-field" data-name="assessable_value" placeholder="auto (+1% landing)">
            </div>
            <div class="col-md-1">
                <label class="form-label small">CD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="cd_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">SD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="sd_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">VAT %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="vat_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">AIT %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="ait_rate" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label small">RD %</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm calc" data-name="rd_rate" value="0">
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
        {{-- The rate the declared value is worked out from (Rates & Taxes → Rates). --}}
        <input type="hidden" data-name="reference_unit_price">
        <input type="hidden" data-name="reference_rate_date">
        <input type="hidden" data-name="reference_usd_rate">
        <div class="ref-hint small mt-1"></div>
        </div>

        <div class="row g-2 mt-1 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted">Charge (rate × CBM)</label>
                <input type="text" class="form-control form-control-sm bg-light out-line-total" readonly value="0.00">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Line Profit</label>
                <input type="text" class="form-control form-control-sm bg-light out-total-profit" readonly value="0.00">
            </div>
            <div class="col-md-8">
                <label class="form-label small">Remarks</label>
                <input type="text" class="form-control form-control-sm" data-name="remarks">
            </div>
        </div>
        </div>
    </div>
</template>

{{-- ===================== Additional cost row template ===================== --}}
<template id="expenseTemplate">
    <div class="row g-2 mb-2 expense-row align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Cost Category</label>
            <select class="form-control form-control-sm" data-name="cost_category_id">
                <option value="">--</option>
                @foreach($costCategories as $cc)<option value="{{ $cc->id }}">{{ $cc->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Title</label>
            <input type="text" class="form-control form-control-sm" data-name="title" placeholder="e.g. port handling, LC commission">
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

    // ---------------- Declared value from the reference rate ----------------
    // Declared value = reference USD per kg × net weight × the dollar rate. It fills
    // itself until somebody types over it; the basis is kept on the item and shown.
    const DOLLAR_RATE = {{ (float) ($dollarRate ?? 0) }};
    const RATES_URL = @json(Route::has('rates.index') ? route('rates.index') : null);
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const field = (block, name) => block.querySelector('[data-name="' + name + '"]');
    const fieldNum = (block, name) => parseFloat(field(block, name)?.value) || 0;
    const dayLabel = value => {
        const [y, m, d] = String(value || '').slice(0, 10).split('-');
        return y && m && d ? d + ' ' + MONTHS[Number(m) - 1] + ' ' + y : '';
    };
    const plain = n => (Math.round((Number(n) || 0) * 10000) / 10000).toLocaleString('en-US', { maximumFractionDigits: 4 });
    const grouped = n => (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function referenceTotal(block, dollarRate) {
        return fieldNum(block, 'reference_unit_price') * fieldNum(block, 'net_weight') * dollarRate;
    }

    function applyReference(block, force) {
        const declared = field(block, 'declared_value');
        if (fieldNum(block, 'reference_unit_price') > 0 && DOLLAR_RATE > 0 && (force || !declared.dataset.touched)) {
            field(block, 'reference_usd_rate').value = DOLLAR_RATE;
            declared.value = money(referenceTotal(block, DOLLAR_RATE));
            delete declared.dataset.touched;
        }
        renderReference(block);
    }

    function renderReference(block) {
        const hint = block.querySelector('.ref-hint');
        const price = fieldNum(block, 'reference_unit_price');
        const declared = field(block, 'declared_value');

        if (!price) {
            hint.innerHTML = field(block, 'hs_code_id').value
                ? '<span class="text-muted">No rate uploaded for this HS code yet.</span>'
                : '';
            return;
        }

        const dollarRate = fieldNum(block, 'reference_usd_rate') || DOLLAR_RATE;
        let text = '<i class="ri-price-tag-2-line"></i> Reference <strong>' + plain(price) + ' USD/kg</strong>' +
            ' · rate of <strong>' + dayLabel(field(block, 'reference_rate_date').value) + '</strong>';

        if (dollarRate > 0) {
            text += ' · ' + plain(price) + ' × ' + plain(fieldNum(block, 'net_weight')) + ' kg × ৳' + plain(dollarRate) +
                ' = ৳' + grouped(referenceTotal(block, dollarRate));
        } else {
            text += ' · <span class="text-danger">set the dollar rate' +
                (RATES_URL ? ' on <a href="' + RATES_URL + '" target="_blank">Rates</a>' : '') + ' to fill the declared value</span>';
        }

        if (declared.dataset.touched) {
            text += ' · <span class="text-warning">declared value edited by hand</span>' +
                (DOLLAR_RATE > 0 ? ' — <a href="#" class="use-reference">use the reference</a>' : '');
        }

        hint.className = 'ref-hint small mt-1 text-primary';
        hint.innerHTML = text;
    }

    // Bangladesh Customs cascade: CD & RD on AV, SD compounds, VAT/AT compound further, AIT on AV.
    function recalcDuty(block) {
        const val = name => parseFloat(block.querySelector('[data-name="' + name + '"]')?.value) || 0;
        const avField = block.querySelector('.av-field');
        if (!avField.dataset.touched) {
            avField.value = money(val('declared_value') * 1.01);
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
        recalcDuty(block);
        recalcTotals();
    }

    function expenseTotal() {
        let sum = 0;
        document.querySelectorAll('#expensesWrap .expense-amount')
            .forEach(el => sum += parseFloat(el.value) || 0);
        return sum;
    }

    // Freight: LCL buys space per CBM, FCL books a whole container at a flat price.
    function freightCost(totalCbm) {
        const type = document.getElementById('freightType').value;
        if (type === 'lcl') { return (parseFloat(document.getElementById('freightRate').value) || 0) * totalCbm; }
        if (type === 'fcl') { return parseFloat(document.getElementById('freightFclAmount').value) || 0; }
        return 0;
    }

    function recalcTotals() {
        const blocks = Array.from(wrap.querySelectorAll('.item-block'));
        const num = (b, name) => parseFloat(b.querySelector('[data-name="' + name + '"]')?.value) || 0;

        let totalCbm = 0, duty = 0, declared = 0;
        blocks.forEach(function (b) {
            totalCbm += num(b, 'cbm');
            declared += num(b, 'declared_value');
            duty += parseFloat(b.querySelector('.out-duty').value) || 0;
        });

        const sellRate = parseFloat(document.getElementById('sellRate').value) || 0;
        const freight = freightCost(totalCbm);
        const charge = sellRate * totalCbm;
        const extras = expenseTotal();
        const cost = freight + duty + extras;
        const profit = charge - cost;

        // Each line takes its share of the charge and of the freight, split by CBM.
        blocks.forEach(function (b) {
            const cbm = num(b, 'cbm');
            const lineCharge = sellRate * cbm;
            const lineFreight = totalCbm > 0 ? freight * cbm / totalCbm : 0;
            const lineDuty = parseFloat(b.querySelector('.out-duty').value) || 0;
            b.querySelector('.out-line-total').value = money(lineCharge);
            b.querySelector('.out-total-profit').value = money(lineCharge - lineDuty - lineFreight);
        });

        const set = (id, value) => { document.getElementById(id).textContent = value; };
        document.getElementById('freightCbm').value = totalCbm.toFixed(4);
        document.getElementById('freightOut').value = money(freight);
        document.getElementById('chargeOut').value = money(charge);
        document.getElementById('marginPerCbm').value = money(sellRate - (totalCbm > 0 ? cost / totalCbm : 0));
        set('itemsCbm', totalCbm.toFixed(4));
        set('itemsDeclared', money(declared));
        set('itemsDuty', money(duty));
        set('customTotal', money(extras));
        set('projFreight', money(freight));
        set('projDuty', money(duty));
        set('projCustom', money(extras));
        set('projCost', money(cost));
        set('askRate', money(sellRate));
        set('askCbm', totalCbm.toFixed(4));
        set('projAsking', money(charge));
        set('projProfit', money(profit));
        set('projMargin', money(charge > 0 ? (profit / charge) * 100 : 0));
    }

    // ---------------- Freight controls ----------------
    const freightType = document.getElementById('freightType');

    function toggleFreightFields() {
        const type = freightType.value;
        document.querySelectorAll('.freight-lcl').forEach(el => el.classList.toggle('d-none', type !== 'lcl'));
        document.querySelectorAll('.freight-fcl').forEach(el => el.classList.toggle('d-none', type !== 'fcl'));
        recalcTotals();
    }

    freightType.addEventListener('change', toggleFreightFields);
    ['freightRate', 'freightFclAmount', 'sellRate'].forEach(id =>
        document.getElementById(id).addEventListener('input', recalcTotals));

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

        // Keep a manually-overridden assessable value; otherwise it follows declared value + 1% landing.
        const avField = block.querySelector('.av-field');
        if (data && data.assessable_value != null && data.assessable_value !== '') {
            const def = (parseFloat(data.declared_value) || 0) * 1.01;
            if (Math.abs(parseFloat(data.assessable_value) - def) > 0.02) { avField.dataset.touched = '1'; }
        }
        avField.addEventListener('input', function () { this.dataset.touched = '1'; });

        // A saved item whose declared value no longer matches its reference was edited by hand.
        const declaredField = field(block, 'declared_value');
        const rateDateField = field(block, 'reference_rate_date');
        if (rateDateField.value) { rateDateField.value = rateDateField.value.slice(0, 10); }
        if (data && fieldNum(block, 'reference_unit_price') > 0) {
            const basis = referenceTotal(block, fieldNum(block, 'reference_usd_rate') || DOLLAR_RATE);
            if (Math.abs((parseFloat(data.declared_value) || 0) - basis) > 0.01) { declaredField.dataset.touched = '1'; }
        } else if (data && (parseFloat(data.declared_value) || 0) > 0) {
            declaredField.dataset.touched = '1';
        }
        declaredField.addEventListener('input', function () { this.dataset.touched = '1'; renderReference(block); });
        field(block, 'net_weight').addEventListener('input', function () { applyReference(block); recalcBlock(block); });
        block.querySelector('.ref-hint').addEventListener('click', function (e) {
            if (e.target.classList.contains('use-reference')) {
                e.preventDefault();
                applyReference(block, true);
                recalcBlock(block);
            }
        });

        block.querySelector('[data-name="description"]').addEventListener('input', renumber);
        block.querySelectorAll('.calc, .dim').forEach(el => el.addEventListener('input', () => recalcBlock(block)));
        block.querySelectorAll('.dim').forEach(el => el.addEventListener('input', () => { autoCbm(block); recalcTotals(); }));
        block.querySelector('[data-name="cbm"]').addEventListener('input', function () { this.dataset.touched = '1'; });
        block.querySelector('.remove-item').addEventListener('click', function () {
            if (wrap.querySelectorAll('.item-block').length > 1) { block.remove(); renumber(); recalcTotals(); }
        });

        wrap.appendChild(node);
        window.attachHsSearch(wrap.lastElementChild, function (b) { renumber(); applyReference(b); recalcBlock(b); });
        renumber();
        renderReference(wrap.lastElementChild);
        recalcBlock(wrap.lastElementChild);
    }

    function renumber() {
        wrap.querySelectorAll('.item-block').forEach(function (b, n) {
            const code = b.querySelector('.hs-search')?.value || '';
            const desc = b.querySelector('[data-name="description"]')?.value || '';
            b.querySelector('.item-title').textContent =
                'Item #' + (n + 1) + (code ? ' — ' + code : '') + (desc ? ' — ' + desc.slice(0, 60) : '');
        });
    }

    document.getElementById('addItemBtn').addEventListener('click', () => addItem());

    // ---------------- Additional predicted costs ----------------
    const expWrap = document.getElementById('expensesWrap');
    const expTmpl = document.getElementById('expenseTemplate');
    let expIndex = 0;

    function addExpense(data) {
        const node = expTmpl.content.cloneNode(true);
        const row = node.querySelector('.expense-row');

        row.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'expenses[' + expIndex + '][' + el.dataset.name + ']';
            if (data && data[el.dataset.name] !== undefined && data[el.dataset.name] !== null) {
                el.value = data[el.dataset.name];
            }
        });

        row.querySelector('.expense-amount').addEventListener('input', recalcTotals);
        row.querySelector('.remove-expense').addEventListener('click', function () {
            row.remove();
            recalcTotals();
        });

        expWrap.appendChild(node);
        expIndex++;
        recalcTotals();
    }

    document.getElementById('addExpenseBtn').addEventListener('click', () => addExpense());

    // ---------------- Packing list parse & fill ----------------
    const fileInput = document.getElementById('packingListInput');
    const parseBtn = document.getElementById('parsePackingBtn');
    const resultBox = document.getElementById('packingParseResult');

    // The parse button is only rendered for roles that may create a quotation.
    if (parseBtn) {
        fileInput.addEventListener('change', () => { parseBtn.disabled = !fileInput.files.length; });
    }

    parseBtn?.addEventListener('click', async function () {
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
            (parseFloat(b.querySelector('[data-name="cbm"]').value) || 0) > 0);
        if (hasContent && !confirm('Replace the current item rows with the packing list items?')) { return; }

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
            '<br>Search an HS code on each row to pull in its description and tax rates.' +
            (data.customer ? '<br>Customer on sheet: ' + data.customer : '') +
            (data.supplier ? ' | Supplier: ' + data.supplier : '') +
            (matches ? '' : '<br><strong>Warning:</strong> imported totals do not match the sheet\'s TOTAL row — please verify.');
    }

    const existing = @json($quotation?->items ?? []);
    if (existing.length) { existing.forEach(addItem); } else { addItem(); }

    const existingExpenses = @json($quotation?->expenses ?? []);
    existingExpenses.forEach(addExpense);
    toggleFreightFields();
});
</script>
