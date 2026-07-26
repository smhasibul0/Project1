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

                <li>
                    <a href="{{ route('dashboard') }}" class="tp-link">
                        <i data-feather="home"></i>
                        <span> Dashboard </span>
                    </a>
                </li>

                <li class="menu-title mt-2">General</li>

                <!-- Contacts -->
                @can('contacts.manage')
                <li>
                    <a href="#contacts" data-bs-toggle="collapse">
                        <i data-feather="users"></i>
                        <span> Contacts </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="contacts">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('suppliers.index') }}" class="tp-link">Suppliers</a>
                            </li>
                            <li>
                                <a href="{{ route('customers.index') }}" class="tp-link">Customers</a>
                            </li>
                            <li>
                                <a href="{{ route('customer.groups') }}" class="tp-link">Customer Groups</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan


                <!-- Products -->
                @can('products.manage')
                <li>
                    <a href="#sidebarProducts" data-bs-toggle="collapse">
                        <i data-feather="package"></i>
                        <span> Products </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarProducts">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('products.index') }}" class="tp-link">List Products</a>
                            </li>
                            <li>
                                <a href="{{ route('products.create') }}" class="tp-link">Add Products</a>
                            </li>
                            <li>
                                <a href="{{ route('categories.index') }}" class="tp-link">Categories</a>
                            </li>
                            <li>
                                <a href="{{ route('brands.index') }}" class="tp-link">Brands</a>
                            </li>
                            <li>
                                <a href="{{ route('units.index') }}" class="tp-link">Units</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Warehouse -->
                @canany(['products.manage', 'expenses.manage'])
                <li>
                    <a href="#sidebarWarehouse" data-bs-toggle="collapse">
                        <i data-feather="archive"></i>
                        <span> Warehouse </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarWarehouse">
                        <ul class="nav-second-level">
                            @can('products.manage')
                            <li>
                                <a href="{{ route('warehouses.index') }}" class="tp-link">Warehouse List</a>
                            </li>
                            @endcan
                            @can('expenses.manage')
                            <li>
                                <a href="{{ route('expense.categories') }}" class="tp-link">Expense Categories</a>
                            </li>
                            @endcan
                            @can('reports.view')
                            <li>
                                <a href="{{ route('reports.warehouse-summary') }}" class="tp-link">Operations Report</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                
                <!-- Quotations -->
                @can('quotations.manage')
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
                            <li>
                                <a href="{{ route('quotations.create') }}" class="tp-link">Add Quotation</a>
                            </li>
                            <li>
                                <a href="{{ route('transportation.modes') }}" class="tp-link">Transportation Modes</a>
                            </li>
                            <li>
                                <a href="{{ route('packing.types') }}" class="tp-link">Packing Types</a>
                            </li>
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
                            @can('orders.manage')
                            <li>
                                <a href="{{ route('orders.create') }}" class="tp-link">Add Order</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- LC -->
                @can('lc.manage')
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
                            <li>
                                <a href="{{ route('lc.create') }}" class="tp-link">Add LC</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Containers -->
                @can('containers.manage')
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
                            <li>
                                <a href="{{ route('container.create') }}" class="tp-link">Add Container</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Order Costs -->
                @can('costs.manage')
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

                <!-- Payment accounts -->
                @can('accounts.manage')
                <li>
                    <a href="#sidebarCharts" data-bs-toggle="collapse">
                        <i data-feather="credit-card"></i>
                        <span> Payment Accounts </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarCharts">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('payment.accounts') }}" class="tp-link">List Accounts</a>
                            </li>
                            @can('reports.view')
                            <li>
                                <a href="{{ route('reports.balance-sheet') }}" class="tp-link">Balance Sheet</a>
                            </li>
                            <li>
                                <a href="{{ route('reports.cash-flow') }}" class="tp-link">Cash Flow</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Access Control -->
                @canany(['users.manage', 'roles.manage'])
                <li class="menu-title mt-2">Access Control</li>
                <li>
                    <a href="#sidebarAccess" data-bs-toggle="collapse">
                        <i data-feather="shield"></i>
                        <span> Users &amp; Roles </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarAccess">
                        <ul class="nav-second-level">
                            @can('users.manage')
                            <li>
                                <a href="{{ route('users.index') }}" class="tp-link">Users</a>
                            </li>
                            @endcan
                            @can('roles.manage')
                            <li>
                                <a href="{{ route('roles.index') }}" class="tp-link">Roles &amp; Permissions</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

                <!-- Reports -->
                @can('reports.view')
                <li>
                    <a href="#sidebarReports" data-bs-toggle="collapse">
                        <i data-feather="bar-chart-2"></i>
                        <span> Reports </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarReports">
                        <ul class="nav-second-level">
                            <li><a href="{{ route('reports.profit-loss') }}" class="tp-link">Profit &amp; Loss</a></li>
                            <li><a href="{{ route('reports.receivables') }}" class="tp-link">Receivables</a></li>
                            <li><a href="{{ route('reports.warehouse-summary') }}" class="tp-link">Warehouse Operations</a></li>
                        </ul>
                    </div>
                </li>
                @endcan

                <!-- Settings -->
                @can('settings.manage')
                <li>
                    <a href="{{ route('settings.company') }}">
                        <i data-feather="settings"></i>
                        <span> Company Settings </span>
                    </a>
                </li>
                @endcan


            </ul>
        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
</div>