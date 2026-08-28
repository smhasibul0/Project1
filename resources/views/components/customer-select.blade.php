@props([
    'customers',
    'selected' => null,
    'name' => 'customer_id',
    'placeholder' => 'Search by name, ID or phone…',
    'required' => false,
])
@php
    $uid = 'csel_'.Str::random(6);
    $selectedContact = $selected ? $customers->firstWhere('id', $selected) : null;
    $selectedLabel = $selectedContact
        ? $selectedContact->name.($selectedContact->business_name ? ' ('.$selectedContact->business_name.')' : '')
        : '';
@endphp

<style>
    .cust-select { position: relative; }
    .cust-select .cust-menu { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(16,24,40,.12); margin-top: 3px; max-height: 260px; overflow-y: auto; }
    .cust-select .cust-item { padding: .5rem .8rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
    .cust-select .cust-item:last-child { border-bottom: none; }
    .cust-select .cust-item:hover, .cust-select .cust-item.active { background: #f4f0fe; }
    .cust-select .cust-item .nm { font-weight: 600; font-size: .85rem; color: #1e293b; }
    .cust-select .cust-item .mt { font-size: .74rem; color: #94a3b8; }
    .cust-select .cust-empty { padding: .7rem .8rem; color: #94a3b8; font-size: .82rem; }
    .cust-select .cust-clear { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; display: none; }
</style>

<div class="cust-select" id="{{ $uid }}">
    <input type="hidden" name="{{ $name }}" value="{{ $selected }}" class="cust-value" @required($required)>
    <input type="text" class="form-control cust-search" autocomplete="off" placeholder="{{ $placeholder }}" value="{{ $selectedLabel }}">
    <i class="ri-close-circle-fill cust-clear"></i>
    <div class="cust-menu" style="display:none;">
        <div class="cust-empty" style="display:none;">No customer found.</div>
        @foreach($customers as $c)
            <div class="cust-item"
                 data-id="{{ $c->id }}"
                 data-label="{{ $c->name }}{{ $c->business_name ? ' ('.$c->business_name.')' : '' }}"
                 data-shipping-mark="{{ $c->shipping_mark }}"
                 data-search="{{ strtolower(trim(($c->contact_code ?? '').' '.$c->name.' '.($c->business_name ?? '').' '.($c->mobile ?? ''))) }}">
                <div class="nm">{{ $c->name }}@if($c->business_name) <span class="text-muted fw-normal">· {{ $c->business_name }}</span>@endif</div>
                <div class="mt">{{ $c->contact_code ?: '—' }}@if($c->shipping_mark) · {{ $c->shipping_mark }}@endif @if($c->mobile) · {{ $c->mobile }}@endif</div>
            </div>
        @endforeach
    </div>
</div>

<script>
(function () {
    const wrap = document.getElementById('{{ $uid }}');
    if (!wrap || wrap.dataset.ready) { return; }
    wrap.dataset.ready = '1';

    const value = wrap.querySelector('.cust-value');
    const search = wrap.querySelector('.cust-search');
    const menu = wrap.querySelector('.cust-menu');
    const empty = wrap.querySelector('.cust-empty');
    const clear = wrap.querySelector('.cust-clear');
    const items = Array.from(wrap.querySelectorAll('.cust-item'));

    const open = () => { menu.style.display = ''; filter(search.value); };
    const close = () => { menu.style.display = 'none'; };
    const toggleClear = () => { clear.style.display = value.value ? '' : 'none'; };

    function filter(q) {
        q = (q || '').toLowerCase().trim();
        let shown = 0;
        items.forEach(function (it) {
            const match = !q || it.dataset.search.includes(q);
            it.style.display = match ? '' : 'none';
            if (match) { shown++; }
        });
        empty.style.display = shown ? 'none' : '';
    }

    search.addEventListener('focus', open);
    search.addEventListener('input', function () {
        // Typing invalidates the current selection until an item is chosen.
        value.value = '';
        toggleClear();
        open();
    });

    // Let the page around this picker react to who was chosen — the order form
    // fills the shipping mark from it.
    function announce(item) {
        wrap.dispatchEvent(new CustomEvent('customer:selected', {
            bubbles: true,
            detail: {
                id: item ? item.dataset.id : '',
                label: item ? item.dataset.label : '',
                shippingMark: item ? (item.dataset.shippingMark || '') : '',
            },
        }));
    }

    items.forEach(function (it) {
        it.addEventListener('click', function () {
            value.value = it.dataset.id;
            search.value = it.dataset.label;
            toggleClear();
            close();
            announce(it);
        });
    });

    clear.addEventListener('click', function () {
        value.value = '';
        search.value = '';
        toggleClear();
        search.focus();
        announce(null);
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) { close(); }
    });

    toggleClear();
})();
</script>
