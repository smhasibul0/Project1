@php
    $company = \App\Models\CompanySetting::current();
    $companyLogo = $company->logo && file_exists(public_path('upload/company/'.$company->logo))
        ? asset('upload/company/'.$company->logo) : null;
@endphp
<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <div class="logo-box">
                <a href="{{ route('dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-light.png') }}" alt="" style="max-height:40px; width:auto; max-width:170px;">
                    </span>
                </a>
                <a href="{{ route('dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ $companyLogo ?? asset('backend/assets/images/logo-dark.png') }}" alt="" style="max-height:40px; width:auto; max-width:170px;">
                    </span>
                </a>
            </div>

            <ul id="side-menu">

                <li class="menu-title">Menu</li>

                @can('dashboard.view')
                <li>
                    <a href="{{ route('dashboard') }}" class="tp-link">
                        <i data-feather="home"></i>
                        <span> Dashboard </span>
                    </a>
                </li>
                @endcan

                <li class="menu-title mt-2">General</li>

                <!-- Customers -->
                @can('customers.view')
                <li>
                    <a href="#customers" data-bs-toggle="collapse">
                        <i data-feather="users"></i>
                        <span> Customers </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="customers">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('customers.index') }}" class="tp-link">Customer List</a>
                            </li>
                            @can('customer-groups.view')
                            <li>
                                <a href="{{ route('customer.groups') }}" class="tp-link">Customer Groups</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan


                <!-- Rates & Taxes: the customs tariff and the reference declared values -->
                @canany(['hs.view', 'rates.view'])
                <li>
                    <a href="#sidebarRates" data-bs-toggle="collapse">
                        <i data-feather="package"></i>
                        <span> Rates &amp; Taxes </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarRates">
                        <ul class="nav-second-level">
                            @can('hs.view')
                            <li>
                                <a href="{{ route('hs.codes') }}" class="tp-link">HS Codes</a>
                            </li>
                            @endcan
                            @can('rates.view')
                            <li>
                                <a href="{{ route('rates.index') }}" class="tp-link">Rates</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                <!-- Warehouse -->
                @canany(['warehouses.view', 'expense-categories.view', 'reports.warehouse-summary'])
                <li>
                    <a href="#sidebarWarehouse" data-bs-toggle="collapse">
                        <i data-feather="archive"></i>
                        <span> Warehouse </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarWarehouse">
                        <ul class="nav-second-level">
                            @can('warehouses.view')
                            <li>
                                <a href="{{ route('warehouses.index') }}" class="tp-link">Warehouse List</a>
                            </li>
                            @endcan
                            @can('expense-categories.view')
                            <li>
                                <a href="{{ route('expense.categories') }}" class="tp-link">Expense Categories</a>
                            </li>
                            @endcan
                            @can('reports.warehouse-summary')
                            <li>
                                <a href="{{ route('reports.warehouse-summary') }}" class="tp-link">Operations Report</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                
                <!-- Quotations -->
                @can('quotations.view')
                <li>
                    <a href="#sidebarQuotations" data-bs-toggle="collapse">
                        <i data-feather="file-text"></i>
                        <span> Quotations </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarQuotations">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('quotations.index') }}" class="tp-link">List Quotations</a>
                            </li>
                            <li>
                                <a href="{{ route('quotation.requests') }}" class="tp-link">Quotation Requests</a>
                            </li>
                            @can('quotations.create')
                            <li>
                                <a href="{{ route('quotations.create') }}" class="tp-link">Add Quotation</a>
                            </li>
                            @endcan
                            @can('transportation-modes.view')
                            <li>
                                <a href="{{ route('transportation.modes') }}" class="tp-link">Transportation Modes</a>
                            </li>
                            @endcan
                            @can('packing-types.view')
                            <li>
                                <a href="{{ route('packing.types') }}" class="tp-link">Packing Types</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Orders -->
                @can('orders.view')
                <li>
                    <a href="#sidebarOrders" data-bs-toggle="collapse">
                        <i data-feather="clipboard"></i>
                        <span> Orders </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarOrders">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('orders.index') }}" class="tp-link">List Orders</a>
                            </li>
                            @can('orders.create')
                            <li>
                                <a href="{{ route('orders.create') }}" class="tp-link">Add Order</a>
                            </li>
                            @endcan
                            @can('orders.scan')
                            <li>
                                <a href="{{ route('scan.index') }}" class="tp-link">Scan Cartons</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- LC -->
                @can('lc.view')
                <li>
                    <a href="#sidebarLc" data-bs-toggle="collapse">
                        <i data-feather="file-text"></i>
                        <span> LC </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarLc">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('lc.index') }}" class="tp-link">List LC</a>
                            </li>
                            @can('lc.create')
                            <li>
                                <a href="{{ route('lc.create') }}" class="tp-link">Add LC</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Containers -->
                @can('containers.view')
                <li>
                    <a href="#sidebarContainers" data-bs-toggle="collapse">
                        <i data-feather="truck"></i>
                        <span> Containers </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarContainers">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('container.index') }}" class="tp-link">List Containers</a>
                            </li>
                            @can('containers.create')
                            <li>
                                <a href="{{ route('container.create') }}" class="tp-link">Add Container</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Order Costs -->
                @can('cost-categories.view')
                <li>
                    <a href="#sidebarCosts" data-bs-toggle="collapse">
                        <i data-feather="dollar-sign"></i>
                        <span> Order Costs </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarCosts">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('cost.categories') }}" class="tp-link">Cost Categories</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Office running costs -->
                @can('office.expenses.view')
                <li>
                    <a href="#sidebarOfficeExpenses" data-bs-toggle="collapse">
                        <i data-feather="home"></i>
                        <span> Office Expenses </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarOfficeExpenses">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('office.expenses') }}" class="tp-link">Monthly Expenses</a>
                            </li>
                            @can('office.cost-types.view')
                            <li>
                                <a href="{{ route('office.cost.types') }}" class="tp-link">Cost Types</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Fixed assets -->
                @can('assets.view')
                <li>
                    <a href="#sidebarAssets" data-bs-toggle="collapse">
                        <i data-feather="package"></i>
                        <span> Asset Management </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarAssets">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('assets.index') }}" class="tp-link">Asset Register</a>
                            </li>
                            @can('assets.depreciation.view')
                            <li>
                                <a href="{{ route('asset.depreciation') }}" class="tp-link">Depreciation</a>
                            </li>
                            @endcan
                            @can('asset-categories.view')
                            <li>
                                <a href="{{ route('asset.categories') }}" class="tp-link">Asset Categories</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Finance management: accounts, borrowing & lending -->
                @canany(['accounts.view', 'loans.view', 'reports.balance-sheet', 'reports.cash-flow'])
                <li>
                    <a href="#sidebarCharts" data-bs-toggle="collapse">
                        <i data-feather="credit-card"></i>
                        <span> Finance Management </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarCharts">
                        <ul class="nav-second-level">
                            @can('accounts.view')
                            <li>
                                <a href="{{ route('payment.accounts') }}" class="tp-link">Payment Accounts</a>
                            </li>
                            @endcan
                            @can('loans.view')
                            <li>
                                <a href="{{ route('loans.index', 'borrowed') }}" class="tp-link">Money Borrowed</a>
                            </li>
                            <li>
                                <a href="{{ route('loans.index', 'lent') }}" class="tp-link">Money Lent</a>
                            </li>
                            @endcan
                            @can('reports.balance-sheet')
                            <li>
                                <a href="{{ route('reports.balance-sheet') }}" class="tp-link">Balance Sheet</a>
                            </li>
                            @endcan
                            @can('reports.cash-flow')
                            <li>
                                <a href="{{ route('reports.cash-flow') }}" class="tp-link">Cash Flow</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                <!-- Access Control -->
                @canany(['users.view', 'roles.view'])
                <li class="menu-title mt-2">Access Control</li>
                <li>
                    <a href="#sidebarAccess" data-bs-toggle="collapse">
                        <i data-feather="shield"></i>
                        <span> Users &amp; Roles </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarAccess">
                        <ul class="nav-second-level">
                            @can('users.view')
                            <li>
                                <a href="{{ route('users.index') }}" class="tp-link">Users</a>
                            </li>
                            @endcan
                            @can('roles.view')
                            <li>
                                <a href="{{ route('roles.index') }}" class="tp-link">Roles &amp; Permissions</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                <!-- Reports -->
                @canany(['reports.profit-loss', 'reports.receivables', 'reports.balance-sheet', 'reports.cash-flow', 'reports.warehouse-summary'])
                <li>
                    <a href="#sidebarReports" data-bs-toggle="collapse">
                        <i data-feather="bar-chart-2"></i>
                        <span> Reports </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarReports">
                        <ul class="nav-second-level">
                            @can('reports.profit-loss')
                            <li><a href="{{ route('reports.profit-loss') }}" class="tp-link">Profit &amp; Loss</a></li>
                            @endcan
                            @can('reports.receivables')
                            <li><a href="{{ route('reports.receivables') }}" class="tp-link">Receivables</a></li>
                            @endcan
                            @can('reports.balance-sheet')
                            <li><a href="{{ route('reports.balance-sheet') }}" class="tp-link">Balance Sheet</a></li>
                            @endcan
                            @can('reports.cash-flow')
                            <li><a href="{{ route('reports.cash-flow') }}" class="tp-link">Cash Flow</a></li>
                            @endcan
                            @can('reports.warehouse-summary')
                            <li><a href="{{ route('reports.warehouse-summary') }}" class="tp-link">Warehouse Operations</a></li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                <!-- Settings -->
                @can('settings.view')
                <li>
                    <a href="{{ route('settings.company') }}">
                        <i data-feather="settings"></i>
                        <span> Company Settings </span>
                    </a>
                </li>
                @endcan

                <!-- Activity Log -->
                @can('activity.view')
                <li>
                    <a href="{{ route('activity.index') }}">
                        <i data-feather="activity"></i>
                        <span> Activity Log </span>
                    </a>
                </li>
                @endcan


            </ul>
        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
</div>