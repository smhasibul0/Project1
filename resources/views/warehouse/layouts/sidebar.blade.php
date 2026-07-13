@php
    $company = \App\Models\CompanySetting::current();
    $companyLogo = $company->logo && file_exists(public_path('upload/company/'.$company->logo))
        ? asset('upload/company/'.$company->logo) : null;
@endphp
<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <div id="sidebar-menu">

            <div class="logo-box">
                <a href="{{ route('warehouse.dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-light.png') }}" alt="" style="max-height:40px; width:auto; max-width:170px;">
                    </span>
                </a>
                <a href="{{ route('warehouse.dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-dark.png') }}" alt="" style="max-height:40px; width:auto; max-width:170px;">
                    </span>
                </a>
            </div>

            <ul id="side-menu">

                <li class="menu-title">Warehouse</li>

                <li>
                    <a href="{{ route('warehouse.dashboard') }}" class="tp-link {{ request()->routeIs('warehouse.dashboard') ? 'active' : '' }}">
                        <i data-feather="home"></i>
                        <span> Dashboard </span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('warehouse.inventory.index') }}" class="tp-link {{ request()->routeIs('warehouse.inventory.*') ? 'active' : '' }}">
                        <i data-feather="box"></i>
                        <span> Inventory </span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('warehouse.orders.index') }}" class="tp-link {{ request()->routeIs('warehouse.orders.*') ? 'active' : '' }}">
                        <i data-feather="truck"></i>
                        <span> Orders to Arrive </span>
                    </a>
                </li>

                <li class="menu-title mt-2">Operations</li>

                <li>
                    <a href="{{ route('warehouse.expenses.index') }}" class="tp-link {{ request()->routeIs('warehouse.expenses.*') ? 'active' : '' }}">
                        <i data-feather="credit-card"></i>
                        <span> Expenses </span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('warehouse.staff.index') }}" class="tp-link {{ request()->routeIs('warehouse.staff.*') ? 'active' : '' }}">
                        <i data-feather="users"></i>
                        <span> Staff &amp; Salary </span>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</div>
