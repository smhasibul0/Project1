@php
    $company = \App\Models\CompanySetting::current();
    $accent = $company->primary_color ?: '#4f46e5';
    $portalUser = auth()->user();
    $logo = $company->logo && file_exists(public_path('upload/company/'.$company->logo))
        ? asset('upload/company/'.$company->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $company->company_name ?: 'Customer Portal' }} @hasSection('title')— @yield('title') @endif</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">
    <style>
        :root {
            --brand: {{ $accent }};
            --brand-soft: color-mix(in srgb, var(--brand) 14%, #fff);
            --brand-ghost: color-mix(in srgb, var(--brand) 7%, #fff);
            --ink: #1e293b;
            --muted: #64748b;
            --line: #e9edf3;
            --bg: #f4f6fb;
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--ink); font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 14px; }
        a { text-decoration: none; }

        /* ---- Top bar ---- */
        .pt-bar { background: #fff; border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 20; }
        .pt-bar .inner { max-width: 1120px; margin: 0 auto; padding: .7rem 1.1rem; display: flex; align-items: center; gap: 1.2rem; }
        .pt-brand { display: flex; align-items: center; gap: .6rem; font-weight: 800; color: var(--ink); font-size: 1.05rem; letter-spacing: -.01em; }
        .pt-brand img { height: 34px; width: auto; max-width: 130px; object-fit: contain; }
        .pt-brand .mark { width: 34px; height: 34px; border-radius: 9px; background: var(--brand); color: #fff; display: grid; place-items: center; font-size: 1.1rem; }
        .pt-nav { display: flex; gap: .2rem; margin-left: .6rem; }
        .pt-nav a { color: var(--muted); font-weight: 600; padding: .45rem .8rem; border-radius: 8px; font-size: .86rem; transition: .15s; }
        .pt-nav a:hover { background: var(--brand-ghost); color: var(--brand); }
        .pt-nav a.active { background: var(--brand-soft); color: var(--brand); }
        .pt-user { margin-left: auto; display: flex; align-items: center; gap: .7rem; }
        .pt-user .who { font-weight: 600; font-size: .84rem; }
        .pt-user .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--brand-soft); color: var(--brand); display: grid; place-items: center; font-weight: 700; }
        .pt-logout { border: 1px solid var(--line); background: #fff; color: var(--muted); border-radius: 8px; padding: .35rem .7rem; font-size: .82rem; font-weight: 600; cursor: pointer; }
        .pt-logout:hover { color: #ef4444; border-color: #fecaca; }

        .pt-wrap { max-width: 1120px; margin: 0 auto; padding: 1.6rem 1.1rem 3rem; }

        /* ---- Page header ---- */
        .pt-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .6rem; margin-bottom: 1.1rem; }
        .pt-head h1 { font-size: 1.35rem; font-weight: 800; margin: 0; letter-spacing: -.02em; }
        .pt-head .sub { color: var(--muted); font-size: .86rem; }

        /* ---- Welcome banner ---- */
        .pt-welcome { background: linear-gradient(120deg, var(--brand), color-mix(in srgb, var(--brand) 65%, #000)); color: #fff; border-radius: 16px; padding: 1.5rem 1.6rem; margin-bottom: 1.2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .pt-welcome h2 { font-size: 1.4rem; font-weight: 800; margin: 0 0 .15rem; }
        .pt-welcome p { margin: 0; opacity: .9; font-size: .9rem; }

        /* ---- Cards ---- */
        .pt-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .pt-card .hd { padding: .95rem 1.15rem; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: .6rem; }
        .pt-card .hd h3 { font-size: .98rem; font-weight: 700; margin: 0; }
        .pt-card .bd { padding: 1.15rem; }
        .pt-card .bd.flush { padding: 0; }

        /* ---- Stat tiles ---- */
        .pt-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1.2rem; }
        .pt-stat { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 1.1rem 1.2rem; display: flex; align-items: center; gap: .9rem; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .pt-stat .ic { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; font-size: 1.35rem; flex: none; }
        .pt-stat .v { font-size: 1.5rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
        .pt-stat .l { color: var(--muted); font-size: .8rem; font-weight: 600; }
        .ic-indigo { background: #eef2ff; color: #4f46e5; }
        .ic-blue { background: #e0f2fe; color: #0284c7; }
        .ic-amber { background: #fef3c7; color: #d97706; }
        .ic-green { background: #dcfce7; color: #16a34a; }
        .ic-red { background: #fee2e2; color: #dc2626; }

        /* ---- Tables ---- */
        .pt-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .pt-table thead th { text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: .7rem 1.15rem; border-bottom: 1px solid var(--line); background: #fbfcfe; }
        .pt-table tbody td { padding: .8rem 1.15rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .pt-table tbody tr:last-child td { border-bottom: none; }
        .pt-table tbody tr:hover { background: #fbfcfe; }
        .pt-table .num { text-align: right; white-space: nowrap; }
        .pt-table tfoot td { padding: .8rem 1.15rem; font-weight: 700; border-top: 2px solid var(--line); }

        /* ---- Pills ---- */
        .pill { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: 999px; font-size: .74rem; font-weight: 700; text-transform: capitalize; }
        .pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .pill-info { background: #e0f2fe; color: #0369a1; }
        .pill-success { background: #dcfce7; color: #15803d; }
        .pill-warning { background: #fef3c7; color: #b45309; }
        .pill-danger { background: #fee2e2; color: #b91c1c; }
        .pill-muted { background: #eef1f6; color: #475569; }
        .pill-brand { background: var(--brand-soft); color: var(--brand); }

        /* ---- Buttons ---- */
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; border-radius: 9px; padding: .5rem .95rem; font-weight: 600; font-size: .85rem; border: 1px solid transparent; cursor: pointer; transition: .15s; }
        .pt-btn-primary { background: var(--brand); color: #fff; }
        .pt-btn-primary:hover { filter: brightness(.94); color: #fff; }
        .pt-btn-ghost { background: #fff; border-color: var(--line); color: var(--muted); }
        .pt-btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
        .pt-btn-success { background: #16a34a; color: #fff; }
        .pt-btn-success:hover { filter: brightness(.94); color: #fff; }
        .pt-btn-danger-o { background: #fff; border-color: #fecaca; color: #dc2626; }
        .pt-btn-danger-o:hover { background: #fef2f2; }
        .pt-btn-sm { padding: .3rem .7rem; font-size: .78rem; }

        .pt-empty { text-align: center; color: var(--muted); padding: 2.5rem 1rem; }
        .pt-empty i { font-size: 2rem; opacity: .4; display: block; margin-bottom: .5rem; }

        .money { font-variant-numeric: tabular-nums; }
        @media (max-width: 640px) { .pt-nav { display: none; } .pt-scroll { overflow-x: auto; } }
    </style>
</head>
<body>
    <header class="pt-bar">
        <div class="inner">
            <a href="{{ route('portal.dashboard') }}" class="pt-brand">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $company->company_name }}">
                @else
                    <span class="mark"><i class="ri-ship-2-line"></i></span>
                    <span>{{ $company->company_name ?: 'Customer Portal' }}</span>
                @endif
            </a>
            <nav class="pt-nav">
                <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('portal.quotations') }}" class="{{ request()->routeIs('portal.quotation*') ? 'active' : '' }}">Quotations</a>
                <a href="{{ route('portal.orders') }}" class="{{ request()->routeIs('portal.order*') || request()->routeIs('portal.orders') ? 'active' : '' }}">Orders</a>
                <a href="{{ route('portal.payments') }}" class="{{ request()->routeIs('portal.payments') ? 'active' : '' }}">Payments</a>
            </nav>
            <div class="pt-user">
                @php $who = $portalUser->contact->name ?? $portalUser->first_name; @endphp
                <span class="avatar">{{ strtoupper(substr($who, 0, 1)) }}</span>
                <span class="who d-none d-sm-inline">{{ $who }}</span>
                <form action="{{ route('logout') }}" method="POST" class="m-0">@csrf<button class="pt-logout"><i class="ri-logout-box-r-line"></i></button></form>
            </div>
        </div>
    </header>

    <main class="pt-wrap">
        @yield('portal')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        toastr.options = { positionClass: 'toast-top-right', progressBar: true, timeOut: 4000 };
        @if(Session::has('success')) toastr.success(@json(Session::get('success'))); @endif
        @if(Session::has('error')) toastr.error(@json(Session::get('error'))); @endif
        @if($errors->any()) @foreach($errors->all() as $error) toastr.error(@json($error)); @endforeach @endif
    </script>
    @yield('portal_scripts')
</body>
</html>
