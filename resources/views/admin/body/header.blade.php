@php
    $canQuotes = auth()->user()->can('quotations.manage');
    $canOrders = auth()->user()->can('orders.view');
    $pendingRequests = $canQuotes ? \App\Models\Quotation::where('status', 'requested')->count() : 0;
    $duesQuery = \App\Models\Order::where('due_amount', '>', 0);
    $ordersWithDue = $canOrders ? (clone $duesQuery)->count() : 0;
    $totalDue = $ordersWithDue ? (float) (clone $duesQuery)->sum('due_amount') : 0;
    $notifCount = $pendingRequests + $ordersWithDue;
    $duesUrl = auth()->user()->can('reports.view') ? route('reports.receivables') : route('orders.index');
@endphp
<div class="topbar-custom">
    <div class="container-xxl">
        <div class="d-flex justify-content-between">
            <ul class="list-unstyled topnav-menu mb-0 d-flex align-items-center">
                <li>
                    <button class="button-toggle-menu nav-link ps-0">
                        <i data-feather="menu" class="noti-icon"></i>
                    </button>
                </li>
                <li class="d-none d-lg-block">
                    <div class="position-relative topbar-search" id="menuSearch">
                        <input type="text" class="form-control bg-light bg-opacity-75 border-light ps-4" placeholder="Search pages… (e.g. orders, LC, customers)" id="menuSearchInput" autocomplete="off">
                        <i class="mdi mdi-magnify fs-16 position-absolute text-muted top-50 translate-middle-y ms-2"></i>
                        <div class="search-results" id="menuSearchResults"></div>
                    </div>
                </li>
            </ul>

            <ul class="list-unstyled topnav-menu mb-0 d-flex align-items-center">

                <li class="d-none d-sm-flex">
                    <button type="button" class="btn nav-link" data-toggle="fullscreen">
                        <i data-feather="maximize" class="align-middle fullscreen noti-icon"></i>
                    </button>
                </li>

                {{-- ===== Real notifications: pending requests + dues ===== --}}
                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        <i data-feather="bell" class="noti-icon"></i>
                        @if($notifCount > 0)<span class="badge bg-danger rounded-circle noti-icon-badge">{{ $notifCount }}</span>@endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-lg">
                        <div class="dropdown-item noti-title">
                            <h5 class="m-0">Notifications @if($notifCount > 0)<span class="badge bg-danger ms-1">{{ $notifCount }}</span>@endif</h5>
                        </div>
                        <div style="max-height:320px; overflow-y:auto;">
                            @if($pendingRequests > 0)
                            <a href="{{ route('quotation.requests') }}" class="dropdown-item d-flex align-items-start gap-2 py-2">
                                <span class="noti-dot" style="background:rgba(var(--brand-rgb,83,122,239),.14); color:var(--brand,#537AEF);"><i class="mdi mdi-file-document-outline"></i></span>
                                <span><span class="fw-semibold d-block">{{ $pendingRequests }} pending quotation request{{ $pendingRequests > 1 ? 's' : '' }}</span><small class="text-muted">Awaiting your quote</small></span>
                            </a>
                            @endif
                            @if($ordersWithDue > 0)
                            <a href="{{ $duesUrl }}" class="dropdown-item d-flex align-items-start gap-2 py-2">
                                <span class="noti-dot" style="background:#fee2e2; color:#dc2626;"><i class="mdi mdi-cash-multiple"></i></span>
                                <span><span class="fw-semibold d-block">{{ $ordersWithDue }} order{{ $ordersWithDue > 1 ? 's' : '' }} with dues</span><small class="text-muted">৳ {{ number_format($totalDue, 2) }} outstanding</small></span>
                            </a>
                            @endif
                            @if($notifCount === 0)
                            <div class="text-center text-muted py-4"><i class="mdi mdi-check-circle-outline d-block" style="font-size:1.8rem;"></i>You're all caught up 🎉</div>
                            @endif
                        </div>
                    </div>
                </li>

                {{-- ===== User ===== --}}
                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle nav-user me-0" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        <img src="{{ (!empty(Auth::user()->photo)) ? url('upload/user_images/'.Auth::user()->photo) : url('upload/no_image.jpg') }}" alt="user-image" class="rounded-circle">
                        <span class="pro-user-name ms-1">
                            {{ Auth::user()->name ?: Auth::user()->first_name }} <i class="mdi mdi-chevron-down"></i>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">{{ trim(Auth::user()->name) ?: Auth::user()->first_name }}</h6>
                            <small class="text-muted">{{ Auth::user()->role->name ?? 'User' }}</small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('admin.profile') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-account-circle-outline fs-16 align-middle"></i>
                            <span>My Account</span>
                        </a>
                        @can('settings.manage')
                        <a href="{{ route('settings.company') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-cog-outline fs-16 align-middle"></i>
                            <span>Company Settings</span>
                        </a>
                        @endcan
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('admin.logout') }}" class="dropdown-item notify-item text-danger">
                            <i class="mdi mdi-location-exit fs-16 align-middle"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</div>

<style>
    .topbar-search .search-results { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 10px 30px rgba(16,24,40,.12); margin-top:6px; max-height:340px; overflow-y:auto; z-index:1050; display:none; padding:.3rem; }
    .topbar-search .search-results.show { display:block; }
    .topbar-search .sr-item { display:flex; align-items:center; gap:.6rem; padding:.5rem .65rem; border-radius:8px; color:#334155; cursor:pointer; font-size:.86rem; text-decoration:none; }
    .topbar-search .sr-item:hover, .topbar-search .sr-item.active { background:rgba(var(--brand-rgb,83,122,239),.1); color:var(--brand,#537AEF); }
    .topbar-search .sr-item i { width:26px; height:26px; border-radius:7px; background:#f1f5f9; display:grid; place-items:center; font-size:.95rem; flex:none; }
    .topbar-search .sr-item:hover i, .topbar-search .sr-item.active i { background:#fff; }
    .topbar-search .sr-empty { padding:.8rem .65rem; color:#94a3b8; font-size:.84rem; text-align:center; }
    .topbar-dropdown .noti-dot { width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-size:1.05rem; flex:none; }
</style>

<script>
window.__adminSearch = [
    { l: 'Dashboard', i: 'ri-home-5-line', u: '{{ route('dashboard') }}', k: 'home overview' },
    @can('orders.view')
    { l: 'Orders', i: 'ri-clipboard-line', u: '{{ route('orders.index') }}', k: 'order list shipment' },
    @endcan
    @can('orders.manage')
    { l: 'Add Order', i: 'ri-add-line', u: '{{ route('orders.create') }}', k: 'new order create' },
    @endcan
    @can('quotations.manage')
    { l: 'Quotations', i: 'ri-file-list-3-line', u: '{{ route('quotations.index') }}', k: 'quote' },
    { l: 'Quotation Requests', i: 'ri-inbox-line', u: '{{ route('quotation.requests') }}', k: 'requests customer pending' },
    { l: 'Add Quotation', i: 'ri-add-line', u: '{{ route('quotations.create') }}', k: 'new quote' },
    { l: 'Transportation Modes', i: 'ri-truck-line', u: '{{ route('transportation.modes') }}', k: 'transport sea air' },
    { l: 'Packing Types', i: 'ri-archive-line', u: '{{ route('packing.types') }}', k: 'packing carton' },
    @endcan
    @can('lc.manage')
    { l: 'LC', i: 'ri-bank-card-line', u: '{{ route('lc.index') }}', k: 'letter of credit' },
    { l: 'Add LC', i: 'ri-add-line', u: '{{ route('lc.create') }}', k: 'new lc' },
    @endcan
    @can('containers.manage')
    { l: 'Containers', i: 'ri-ship-line', u: '{{ route('container.index') }}', k: 'shipment container' },
    { l: 'Add Container', i: 'ri-add-line', u: '{{ route('container.create') }}', k: 'new container' },
    @endcan
    @can('costs.manage')
    { l: 'Cost Categories', i: 'ri-price-tag-3-line', u: '{{ route('cost.categories') }}', k: 'costs' },
    @endcan
    @can('products.manage')
    { l: 'Products', i: 'ri-box-3-line', u: '{{ route('products.index') }}', k: 'catalogue inventory' },
    { l: 'Add Product', i: 'ri-add-line', u: '{{ route('products.create') }}', k: 'new product' },
    { l: 'Categories', i: 'ri-folder-line', u: '{{ route('categories.index') }}', k: 'category' },
    { l: 'Brands', i: 'ri-bookmark-line', u: '{{ route('brands.index') }}', k: 'brand' },
    { l: 'Units', i: 'ri-ruler-line', u: '{{ route('units.index') }}', k: 'unit measure' },
    { l: 'Warehouses', i: 'ri-store-2-line', u: '{{ route('warehouses.index') }}', k: 'warehouse stock' },
    @endcan
    @can('contacts.manage')
    { l: 'Suppliers', i: 'ri-user-2-line', u: '{{ route('suppliers.index') }}', k: 'vendor' },
    { l: 'Customers', i: 'ri-user-3-line', u: '{{ route('customers.index') }}', k: 'client buyer' },
    { l: 'Customer Groups', i: 'ri-group-line', u: '{{ route('customer.groups') }}', k: 'groups' },
    @endcan
    @can('accounts.manage')
    { l: 'Payment Accounts', i: 'ri-bank-line', u: '{{ route('payment.accounts') }}', k: 'cash bank account' },
    @endcan
    @can('reports.view')
    { l: 'Profit & Loss', i: 'ri-line-chart-line', u: '{{ route('reports.profit-loss') }}', k: 'report pnl profit' },
    { l: 'Receivables', i: 'ri-time-line', u: '{{ route('reports.receivables') }}', k: 'report dues aging' },
    { l: 'Cash Flow', i: 'ri-exchange-line', u: '{{ route('reports.cash-flow') }}', k: 'report cash' },
    { l: 'Balance Sheet', i: 'ri-scales-3-line', u: '{{ route('reports.balance-sheet') }}', k: 'report balance' },
    @endcan
    @can('users.manage')
    { l: 'Users', i: 'ri-team-line', u: '{{ route('users.index') }}', k: 'staff access' },
    @endcan
    @can('roles.manage')
    { l: 'Roles & Permissions', i: 'ri-shield-user-line', u: '{{ route('roles.index') }}', k: 'permissions access' },
    @endcan
    @can('settings.manage')
    { l: 'Company Settings', i: 'ri-settings-3-line', u: '{{ route('settings.company') }}', k: 'settings logo brand' },
    @endcan
];

document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('menuSearch');
    const input = document.getElementById('menuSearchInput');
    const box = document.getElementById('menuSearchResults');
    if (!wrap || !input || !box) { return; }
    const items = window.__adminSearch || [];
    let visible = [];

    function render(q) {
        q = q.toLowerCase().trim();
        visible = items.filter(it => (it.l + ' ' + it.k).toLowerCase().includes(q)).slice(0, 8);
        if (!q) { box.classList.remove('show'); return; }
        box.innerHTML = visible.length
            ? visible.map((it, n) => '<a class="sr-item' + (n === 0 ? ' active' : '') + '" href="' + it.u + '"><i class="' + it.i + '"></i>' + it.l + '</a>').join('')
            : '<div class="sr-empty">No pages match "' + q.replace(/</g, '&lt;') + '"</div>';
        box.classList.add('show');
    }

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('focus', () => { if (input.value) { render(input.value); } });
    input.addEventListener('keydown', function (e) {
        const active = box.querySelector('.sr-item.active');
        const all = Array.from(box.querySelectorAll('.sr-item'));
        if (e.key === 'Enter' && active) { e.preventDefault(); window.location = active.getAttribute('href'); }
        else if (e.key === 'ArrowDown' && all.length) { e.preventDefault(); const i = all.indexOf(active); (all[i + 1] || all[0]).classList.add('active'); if (active && all[i + 1]) { active.classList.remove('active'); } }
        else if (e.key === 'ArrowUp' && all.length) { e.preventDefault(); const i = all.indexOf(active); (all[i - 1] || all[all.length - 1]).classList.add('active'); if (active && all[i - 1]) { active.classList.remove('active'); } }
        else if (e.key === 'Escape') { box.classList.remove('show'); }
    });
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) { box.classList.remove('show'); } });
});
</script>
