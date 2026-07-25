<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>@yield('title', 'Warehouse') · {{ \App\Models\CompanySetting::current()->company_name ?: 'Warehouse Portal' }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        @php
            $company = \App\Models\CompanySetting::current();
            $accent = $company->primary_color ?: '#537AEF';
            $hx = ltrim($accent, '#');
            $accentRgb = strlen($hx) === 6 ? hexdec(substr($hx, 0, 2)).', '.hexdec(substr($hx, 2, 2)).', '.hexdec(substr($hx, 4, 2)) : '83, 122, 239';
            $companyLogo = $company->logo && file_exists(public_path('upload/company/'.$company->logo)) ? asset('upload/company/'.$company->logo) : null;
        @endphp
        <link rel="shortcut icon" href="{{ $companyLogo ?? asset('backend/assets/images/favicon.ico') }}">
        <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />
        <link href="{{ asset('backend/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('backend/assets/css/custom.css') }}" rel="stylesheet" type="text/css" />
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" type="text/css">
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">

        <style>
            :root { --brand: {{ $accent }}; --brand-rgb: {{ $accentRgb }}; --bs-primary: {{ $accent }}; --bs-primary-rgb: {{ $accentRgb }}; }
            .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: rgba(var(--brand-rgb), .9); --bs-btn-hover-border-color: rgba(var(--brand-rgb), .9); --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand); }
            .btn-outline-primary { --bs-btn-color: var(--brand); --bs-btn-border-color: var(--brand); --bs-btn-hover-bg: var(--brand); --bs-btn-hover-border-color: var(--brand); --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand); }
            .text-primary { color: var(--brand) !important; }
            .bg-primary, .badge.bg-primary { background-color: var(--brand) !important; }
            .form-check-input:checked { background-color: var(--brand); border-color: var(--brand); }
            .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 .15rem rgba(var(--brand-rgb), .2); }
            #side-menu .menuitem-active > a, #side-menu li a.active { color: var(--brand) !important; }
        </style>
    </head>

    <body data-menu-color="light" data-sidebar="default">
        <div id="app-layout">
            @include('warehouse.layouts.header')
            @include('warehouse.layouts.sidebar')

            <div class="content-page">
                @yield('warehouse')
                @include('admin.body.footer')
            </div>
        </div>

        <script src="{{ asset('backend/assets/libs/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/simplebar/simplebar.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/node-waves/waves.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/feather-icons/feather.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
        <script src="{{ asset('backend/assets/js/app.js') }}"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        <script>
            @if(Session::has('success')) toastr.success(@json(Session::get('success'))); @endif
            @if(Session::has('error')) toastr.error(@json(Session::get('error'))); @endif
            @if($errors->any()) @foreach($errors->all() as $error) toastr.error(@json($error)); @endforeach @endif
        </script>
        @yield('warehouse_scripts')
    </body>
</html>
