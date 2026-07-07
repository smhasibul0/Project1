@extends('portal.layouts.master')
@section('title', 'New Request')
@section('portal')

<div class="pt-head">
    <div>
        <h1>New Quotation Request</h1>
        <div class="sub">Tell us what you want to source — we'll send you rates.</div>
    </div>
    <a href="{{ route('portal.quotations') }}" class="pt-btn pt-btn-ghost">Back</a>
</div>

<form action="{{ route('portal.quotation.store') }}" method="POST">
    @csrf
    <div class="pt-card mb-3">
        <div class="hd">
            <h3>Items you want to source</h3>
            <button type="button" class="pt-btn pt-btn-primary pt-btn-sm" id="addItemBtn"><i class="ri-add-line"></i> Add Item</button>
        </div>
        <div class="bd">
            <div id="itemsWrap"></div>
        </div>
    </div>

    <div class="pt-card mb-3">
        <div class="bd">
            <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" name="remarks" rows="2" placeholder="Anything else we should know?"></textarea>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="pt-btn pt-btn-primary"><i class="ri-send-plane-line"></i> Submit Request</button>
        <a href="{{ route('portal.quotations') }}" class="pt-btn pt-btn-ghost">Cancel</a>
    </div>
</form>

<template id="itemTemplate">
    <div class="border rounded p-3 mb-3 item-block position-relative" style="border-color:var(--line)!important;">
        <button type="button" class="btn-close position-absolute remove-item" style="top:.65rem; right:.65rem;"></button>
        <div class="row g-2">
            <div class="col-md-5"><label class="form-label small fw-semibold">Item / Description</label><input type="text" class="form-control form-control-sm" data-name="description" placeholder="e.g. LED Bulbs 9W"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Category</label><select class="form-control form-control-sm" data-name="category_id"><option value="">--</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">HS Code</label><input type="text" class="form-control form-control-sm" data-name="hs_code"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Quantity</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-name="package_quantity" value="1"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Net Weight (kg)</label><input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="net_weight"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Volume (CBM)</label><input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-name="cbm"></div>
        </div>
    </div>
</template>
@endsection

@section('portal_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('itemsWrap');
    const tmpl = document.getElementById('itemTemplate');
    let i = 0;

    function addItem() {
        const idx = i++;
        const node = tmpl.content.cloneNode(true);
        const block = node.querySelector('.item-block');
        block.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = 'items[' + idx + '][' + el.dataset.name + ']';
        });
        block.querySelector('.remove-item').addEventListener('click', function () {
            if (wrap.querySelectorAll('.item-block').length > 1) { block.remove(); }
        });
        wrap.appendChild(node);
    }

    document.getElementById('addItemBtn').addEventListener('click', addItem);
    addItem();
});
</script>
@endsection
