@php
    $lc = $lc ?? null;
    $order = $order ?? $lc?->order ?? null;
    $selectedType = old('type', $lc?->type ?? $type ?? 'lc');
    $selectedLabel = \App\Models\Lc::types()[$selectedType] ?? 'LC';
@endphp

{{-- ===================== LC / CAD / TT and PI details ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0"><span class="type-label">{{ $selectedLabel }}</span> &amp; Purchase Invoice</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select class="form-control" name="type" id="lcType" required>
                @foreach($types as $key => $name)
                    <option value="{{ $key }}" data-label="{{ \App\Models\Lc::types()[$key] }}" @selected($selectedType === $key)>{{ \App\Models\Lc::types()[$key] }} — {{ $name }}</option>
                @endforeach
            </select>
            @if($lc)<small class="text-muted">Changing the type gives it that type's next code.</small>@endif
        </div>
        <div class="col-md-4">
            <label class="form-label">Linked Order <small class="text-muted">(optional)</small></label>
            <select class="form-control" name="order_id">
                <option value="">— Standalone (no order) —</option>
                @foreach($orders as $o)
                    <option value="{{ $o->id }}" @selected(old('order_id', $order?->id) == $o->id)>{{ $o->order_no }} — {{ $o->customer->name ?? '—' }}</option>
                @endforeach
            </select>
            <small class="text-muted">Linking an order folds its charges into that order's cost &amp; profit.</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">Shipper (PI issuer)</label>
            <input type="text" class="form-control" name="shipper" value="{{ old('shipper', $lc?->shipper) }}"
                   placeholder="Who issued the proforma invoice">
        </div>

        <div class="col-md-3">
            <label class="form-label"><span class="type-label">{{ $selectedLabel }}</span> Number</label>
            <input type="text" class="form-control" name="lc_number" value="{{ old('lc_number', $lc?->lc_number) }}" placeholder="e.g. 9326160005">
        </div>
        <div class="col-md-3">
            <label class="form-label">PI Date</label>
            <input type="date" class="form-control" name="pi_date" value="{{ old('pi_date', optional($lc?->pi_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">PI No</label>
            <input type="text" class="form-control" name="pi_no" value="{{ old('pi_no', $lc?->pi_no) }}" placeholder="e.g. RTCHW-01/2026">
        </div>
        <div class="col-md-3">
            <label class="form-label" id="bankLabel">{{ $selectedType === 'lc' ? 'Opening Bank' : 'Bank' }}</label>
            <input type="text" class="form-control" name="opening_bank" value="{{ old('opening_bank', $lc?->opening_bank) }}" placeholder="e.g. Janata Bank">
        </div>

        <div class="col-md-6">
            <label class="form-label">PI Document</label>
            <input type="file" class="form-control" name="pi_document" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
            <small class="text-muted">
                .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png
                @if($lc?->pi_document)· <a href="{{ asset('upload/lc/'.$lc->pi_document) }}" target="_blank">current file</a>@endif
            </small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Container No</label>
            <input type="text" class="form-control" name="container_no" value="{{ old('container_no', $lc?->container_no) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Commodity</label>
            <input type="text" class="form-control" name="commodity" value="{{ old('commodity', $lc?->commodity) }}">
        </div>
    </div>
</div>

{{-- ===================== Amounts ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Amounts &amp; Charges</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Currency</label>
            <select class="form-control" name="currency">
                @foreach(['USD', 'BDT', 'CNY', 'EUR'] as $cur)
                    <option value="{{ $cur }}" @selected(old('currency', $lc?->currency ?? 'USD') === $cur)>{{ $cur }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Inv. Amount (Sent)</label>
            <input type="number" step="0.01" min="0" class="form-control calc-amt" id="invoiceAmount" name="invoice_amount" value="{{ old('invoice_amount', $lc?->invoice_amount ?? 0) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Net Amt. Received</label>
            <input type="number" step="0.01" min="0" class="form-control calc-amt" id="netReceived" name="net_amount_received" value="{{ old('net_amount_received', $lc?->net_amount_received) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Bank Charges <small class="text-muted">(auto)</small></label>
            <input type="text" class="form-control bg-light" id="bankCharges" value="{{ number_format((float) ($lc?->bank_charges ?? 0), 2) }}" readonly>
        </div>

        <div class="col-md-3">
            <label class="form-label">China USD Sell Rate</label>
            <input type="number" step="0.0001" min="0" class="form-control" name="usd_sell_rate" value="{{ old('usd_sell_rate', $lc?->usd_sell_rate) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">USD Sell Date</label>
            <input type="date" class="form-control" name="usd_sell_date" value="{{ old('usd_sell_date', optional($lc?->usd_sell_date)->format('Y-m-d')) }}">
        </div>
    </div>
</div>

{{-- ===================== Status ===================== --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Status</h6></div>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-control" name="lc_status">
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('lc_status', $lc?->lc_status ?? 'draft') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Released Date</label>
            <input type="date" class="form-control" name="released_date" value="{{ old('released_date', optional($lc?->released_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2">{{ old('remarks', $lc?->remarks) }}</textarea>
        </div>
    </div>
</div>

<div class="d-flex gap-2 my-4">
    <button type="submit" class="btn btn-primary px-4">{{ $lc ? 'Update' : 'Save' }} <span class="type-label">{{ $selectedLabel }}</span></button>
    <a href="{{ route('lc.index', $selectedType) }}" class="btn btn-secondary">Cancel</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inv = document.getElementById('invoiceAmount');
    const net = document.getElementById('netReceived');
    const out = document.getElementById('bankCharges');

    function recalc() {
        const invoice = parseFloat(inv.value) || 0;
        const received = net.value === '' ? null : (parseFloat(net.value) || 0);
        out.value = received === null ? '0.00' : (invoice - received).toFixed(2);
    }

    [inv, net].forEach(el => el.addEventListener('input', recalc));
    recalc();

    // The labels follow the type picked: LC, CAD or TT.
    const type = document.getElementById('lcType');
    type.addEventListener('change', function () {
        const label = type.selectedOptions[0].dataset.label;
        document.querySelectorAll('.type-label').forEach(el => el.textContent = label);
        document.getElementById('bankLabel').textContent = type.value === 'lc' ? 'Opening Bank' : 'Bank';
    });
});
</script>
