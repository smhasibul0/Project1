@php
    $whUser = auth()->user();
    $activeWarehouse = \App\Support\CurrentWarehouse::get();
    $isManaging = \App\Support\CurrentWarehouse::isManaging();
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
                <li class="d-none d-lg-flex align-items-center">
                    <span class="badge bg-primary-subtle text-primary fs-13"><i class="ri-store-2-line me-1"></i>{{ $activeWarehouse->name ?? 'Warehouse' }}</span>
                </li>
                @if($isManaging && auth()->user()->can('warehouses.enter'))
                <li class="d-flex align-items-center ms-2">
                    <a href="{{ route('warehouse.manage.exit') }}" class="btn btn-sm btn-outline-danger">
                        <i class="ri-logout-box-line me-1"></i> Exit to Admin
                    </a>
                </li>
                @endif
            </ul>

            <ul class="list-unstyled topnav-menu mb-0 d-flex align-items-center">
                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle nav-user me-0" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        <img src="{{ (!empty($whUser->photo)) ? url('upload/user_images/'.$whUser->photo) : url('upload/no_image.jpg') }}" alt="user-image" class="rounded-circle">
                        <span class="pro-user-name ms-1">
                            {{ $whUser->name ?: $whUser->first_name }} <i class="mdi mdi-chevron-down"></i>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">{{ trim($whUser->name) ?: $whUser->first_name }}</h6>
                            <small class="text-muted">{{ $activeWarehouse->name ?? 'Warehouse' }}</small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('admin.profile') }}" class="dropdown-item notify-item">
                            <i class="mdi mdi-account-circle-outline fs-16 align-middle"></i>
                            <span>My Account</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item notify-item text-danger">
                                <i class="mdi mdi-location-exit fs-16 align-middle"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>
