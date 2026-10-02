@php
    $company = \App\Models\CompanySetting::current();
    $accent = $company->primary_color ?: '#537AEF';
    $logo = $company->logo && file_exists(public_path('upload/company/'.$company->logo))
        ? asset('upload/company/'.$company->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Sign in') · {{ $company->company_name ?: 'Portal' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ $logo ?? asset('backend/assets/images/favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: {{ $accent }}; --accent-dark: color-mix(in srgb, var(--accent) 62%, #000); --accent-soft: color-mix(in srgb, var(--accent) 12%, #fff); }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: #1e293b; background: #f7f8fc; }
        .auth-wrap { min-height: 100vh; display: flex; }

        /* Brand panel */
        .auth-brand { flex: 0 0 46%; background: linear-gradient(140deg, var(--accent), var(--accent-dark)); color: #fff; padding: 3.5rem; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .auth-brand::before { content: ''; position: absolute; width: 460px; height: 460px; border-radius: 50%; background: rgba(255,255,255,.08); top: -160px; right: -140px; }
        .auth-brand::after { content: ''; position: absolute; width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.06); bottom: -120px; left: -100px; }
        .auth-brand .inner { position: relative; z-index: 1; max-width: 420px; }
        .auth-brand .mark { width: 54px; height: 54px; border-radius: 15px; background: rgba(255,255,255,.16); display: grid; place-items: center; font-size: 1.6rem; margin-bottom: 1.6rem; }
        .auth-brand .mark-logo { width: auto; height: auto; max-width: 240px; padding: .65rem .9rem; background: #fff; }
        .auth-brand .mark-logo img { display: block; max-height: 60px; max-width: 100%; object-fit: contain; }
        .auth-brand h1 { font-size: 2rem; font-weight: 800; line-height: 1.2; margin: 0 0 .8rem; letter-spacing: -.02em; }
        .auth-brand p.lead { opacity: .9; font-size: 1rem; margin: 0 0 2rem; }
        .auth-feat { list-style: none; padding: 0; margin: 0; display: grid; gap: .9rem; }
        .auth-feat li { display: flex; align-items: center; gap: .7rem; font-size: .92rem; opacity: .95; }
        .auth-feat i { width: 30px; height: 30px; border-radius: 8px; background: rgba(255,255,255,.18); display: grid; place-items: center; font-size: 1rem; flex: none; }

        /* Form panel */
        .auth-form { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1.25rem; }
        .auth-card { width: 100%; max-width: 440px; }
        .auth-card .brand-top { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.6rem; }
        .auth-card .brand-top img { max-height: 42px; max-width: 150px; object-fit: contain; }
        .auth-card .brand-top .m2 { width: 40px; height: 40px; border-radius: 11px; background: var(--accent); color: #fff; display: grid; place-items: center; font-size: 1.15rem; }
        .auth-card .brand-top .nm { font-weight: 800; font-size: 1.1rem; }
        .auth-card h2 { font-size: 1.55rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .3rem; }
        .auth-card .sub { color: #64748b; font-size: .9rem; margin-bottom: 1.5rem; }
        .auth-card label { font-weight: 600; font-size: .82rem; color: #334155; margin-bottom: .35rem; }
        .auth-card .input-group-text { background: #fff; border-right: 0; color: #94a3b8; }
        .auth-card .form-control { border-left: 0; padding: .62rem .8rem; }
        .auth-card .input-group:focus-within { box-shadow: 0 0 0 3px var(--accent-soft); border-radius: 9px; }
        .auth-card .input-group:focus-within .form-control, .auth-card .input-group:focus-within .input-group-text { border-color: var(--accent); }
        .auth-card .form-control:focus { box-shadow: none; }
        .auth-card .input-group > :first-child { border-top-left-radius: 9px; border-bottom-left-radius: 9px; }
        .auth-card .input-group > :last-child { border-top-right-radius: 9px; border-bottom-right-radius: 9px; }
        .auth-card .form-control, .auth-card .input-group-text { border-color: #dfe3eb; }
        .btn-accent { background: var(--accent); border: none; color: #fff; font-weight: 700; border-radius: 10px; padding: .7rem; width: 100%; transition: filter .15s; }
        .btn-accent:hover { filter: brightness(.93); color: #fff; }
        .auth-card a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .auth-card .foot { text-align: center; color: #64748b; font-size: .9rem; margin-top: 1.4rem; }
        .field-err { color: #dc2626; font-size: .78rem; margin-top: .3rem; display: block; }
        .auth-status { background: #dcfce7; color: #15803d; border-radius: 9px; padding: .6rem .8rem; font-size: .85rem; margin-bottom: 1rem; }

        @media (max-width: 991px) { .auth-brand { display: none; } .auth-form { background: #f7f8fc; } }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-brand">
            <div class="inner">
                @if($logo)
                    <div class="mark mark-logo"><img src="{{ $logo }}" alt="{{ $company->company_name }}"></div>
                @else
                    <div class="mark"><i class="ri-ship-2-line"></i></div>
                @endif
                <h1>{{ $company->company_name ?: 'Import Sourcing & Freight' }}</h1>
                <p class="lead">Source, ship and track your imports end to end — quotations, orders, LCs, containers and payments in one place.</p>
                <ul class="auth-feat">
                    <li><i class="ri-file-list-3-line"></i> Request &amp; approve quotations</li>
                    <li><i class="ri-ship-line"></i> Track every shipment's status</li>
                    <li><i class="ri-wallet-3-line"></i> See payments, dues &amp; reports</li>
                </ul>
            </div>
        </div>

        <div class="auth-form">
            <div class="auth-card">
                <div class="brand-top">
                    @if($logo)<img src="{{ $logo }}" alt="{{ $company->company_name }}">
                    @else<span class="m2"><i class="ri-ship-2-line"></i></span><span class="nm">{{ $company->company_name ?: 'Portal' }}</span>@endif
                </div>
                @yield('auth')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
