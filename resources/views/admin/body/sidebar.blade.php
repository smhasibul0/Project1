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

                <li class="menu-title">Pages</li>

                <li>
                    <a href="#sidebarAuth" data-bs-toggle="collapse">
                        <i data-feather="users"></i>
                        <span> Authentication </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarAuth">
                        <ul class="nav-second-level">
                            <li>
                                <a href="auth-login.html" class="tp-link">Log In</a>
                            </li>
                            <li>
                                <a href="auth-register.html" class="tp-link">Register</a>
                            </li>
                            
                        </ul>
                    </div>
                </li>

                <li>
                    <a href="#sidebarError" data-bs-toggle="collapse">
                        <i data-feather="alert-octagon"></i>
                        <span> Error Pages </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarError">
                        <ul class="nav-second-level">
                            <li>
                                <a href="error-404.html" class="tp-link">Error 404</a>
                            </li>
                            <li>
                                <a href="error-500.html" class="tp-link">Error 500</a>
                            </li>
                            
                        </ul>
                    </div>
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

                
                <!-- Purchases -->
                <li>
                    <a href="#sidebarIcons" data-bs-toggle="collapse">
                        <i data-feather="shopping-bag"></i>
                        <span> Purchases </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarIcons">
                        <ul class="nav-second-level">
                            <li>
                                <a href="#" class="tp-link">List Purchases</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Add Purchase</a>
                            </li>
                            
                        </ul>
                    </div>
                </li>
                

                <!-- Sell -->
                <li>
                    <a href="#sidebarForms" data-bs-toggle="collapse">
                        <i data-feather="shopping-cart"></i>
                        <span> Sell </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarForms">
                        <ul class="nav-second-level">
                            <li>
                                <a href="#" class="tp-link">All Sales</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Add Sale</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">List POS</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">POS</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">List Sell Return</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Add Sell Return</a>
                            </li>
                        </ul>
                    </div>
                </li>


                <!-- Expenses -->
                <li>
                    <a href="#sidebarTables" data-bs-toggle="collapse">
                        <i data-feather="dollar-sign"></i>
                        <span> Expenses </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarTables">
                        <ul class="nav-second-level">
                            <li>
                                <a href="#" class="tp-link">List Expenses</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Add Expense</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Expense Categories</a>
                            </li>
                            
                        </ul>
                    </div>
                </li>


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
                            <li>
                                <a href="#" class="tp-link">Balance Sheet</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Cash Flow</a>
                            </li>
                            <li>
                                <a href="#" class="tp-link">Payment Account Report</a>
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


                <!-- Reports -->
                <li>
                    <a href="#sidebarMaps" data-bs-toggle="collapse">
                        <i data-feather="clipboard"></i>
                        <span> Reports </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarMaps">
                        <ul class="nav-second-level">
                            <li>
                                <a href="extended-carousel.html" class="tp-link">Profit Loss Report</a>
                            </li>
                            <li>
                                <a href="extended-notifications.html" class="tp-link">Purchase and Sell Report</a>
                            </li>
                            
                        </ul>
                    </div>
                </li>

                
            </ul>
        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
</div>