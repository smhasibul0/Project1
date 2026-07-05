<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <div class="logo-box">
                <a href="{{ route('dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('backend/assets/images/logo-light.png') }}" alt="" height="40">
                    </span>
                </a>
                <a href="{{ route('dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ asset('backend/assets/images/logo-sm.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('backend/assets/images/logo-dark.png') }}" alt="" height="40">
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
                            <li>
                                <a href="{{ route('warehouses.index') }}" class="tp-link">Warehouses</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endcan

                
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