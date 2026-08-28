@extends('portal.layouts.master')
@section('title', 'New Request')
@section('portal')

<div class="pt-head">
    <div>
        <h1>New Quotation Request</h1>
        <div class="sub">Tell us what you're shipping — we'll send you a rate per CBM.</div>
    </div>
    <a href="{{ route('portal.quotations') }}" class="pt-btn pt-btn-ghost">Back</a>
</div>

<form action="{{ route('portal.quotation.store') }}" method="POST">
    @csrf
    <div class="pt-card mb-3">
        <div class="hd">
            <h3>What are you shipping?</h3>
            <button type="button" class="pt-btn pt-btn-primary pt-btn-sm" id="addItemBtn"><i class="ri-add-line"></i> Add item</button>
        </div>
        <div class="bd">
            <div id="itemsWrap"></div>
            <small class="text-muted">
                Search the HS code if you know it — it names the goods for customs. Otherwise just describe them and we'll classify them for you.
            </small>
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

<x-hs-search />

<template id="itemTemplate">
    <div class="border rounded p-3 mb-3 item-block position-relative" style="border-color:var(--line)!important;">
        <button type="button" class="btn-close position-absolute remove-item" style="top:.65rem; right:.65rem;"></button>
        <input type="hidden" data-name="hs_code_id">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">HS Code <span class="text-muted fw-normal">(optional)</span></label>
                <div class="hs-search-wrap">
                    <input type="text" class="form-control form-control-sm hs-search" data-name="hs_code" placeholder="search a code or keyword" autocomplete="off">
                    <div class="hs-results"></div>
                </div>
            </div>
            <div class="col-md-5"><label class="form-label small fw-semibold">Item / Description</label><input type="text" class="form-control form-control-sm" data-name="description" placeholder="e.g. LED Bulbs 9W"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Packages</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-name="package_quantity" value="1"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Net Weight (kg)</label><input type="number" step="0.001" min="0" class="form-control form-control-sm" data-name="net_weight"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Volume (CBM)</label><input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-name="cbm"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold">Invoice Value <span class="text-muted fw-normal">(for duty)</span></label><input type="number" step="0.01" min="0" class="form-control form-control-sm" data-name="declared_value"></div>
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
        block.querySelectorAll('[data-name]').forEach(el => el.name = 'items[' + idx + '][' + el.dataset.name + ']');

        block.querySelector('.remove-item').addEventListener('click', function () {
            if (wrap.querySelectorAll('.item-block').length > 1) { block.remove(); }
        });

        wrap.appendChild(node);
        window.attachHsSearch(wrap.lastElementChild);
    }

    document.getElementById('addItemBtn').addEventListener('click', addItem);
    addItem();
});
</script>
@endsection
