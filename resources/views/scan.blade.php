<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scan a carton</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    {{-- Served from our own server: the scanner has to work on a warehouse
         connection that can reach this site and nothing else. --}}
    <script src="{{ asset('backend/assets/libs/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 1rem; }
        .wrap { max-width: 560px; margin: 0 auto; }
        .head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .head h1 { font-size: 1.15rem; margin: 0; }
        .head a { color: #94a3b8; text-decoration: none; font-size: .85rem; }
        .card { background: #fff; color: #1e293b; border-radius: 14px; padding: 1.2rem; margin-bottom: 1rem; }
        #reader { border-radius: 14px; overflow: hidden; background: #000; }
        #reader video { width: 100% !important; display: block; }
        button { background: #7c5cf0; color: #fff; border: 0; border-radius: 10px; padding: .85rem 1.2rem; font-weight: 700; cursor: pointer; font-size: 1rem; width: 100%; }
        button:hover { background: #6a49e0; }
        button.ghost { background: #e2e8f0; color: #334155; }
        label { display: block; font-size: .82rem; font-weight: 700; margin: .9rem 0 .3rem; color: #334155; }
        select, input { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: .7rem .8rem; font-size: 1rem; background: #fff; color: #1e293b; }
        .mark { font-size: 1.6rem; font-weight: 800; letter-spacing: .03em; }
        .sub { color: #64748b; font-size: .88rem; margin-top: .15rem; }
        .counts { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin: .9rem 0; }
        .count { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .5rem .6rem; }
        .count .n { font-weight: 800; }
        .count .l { font-size: .72rem; color: #64748b; }
        .count.done { background: #ecfdf5; border-color: #a7f3d0; }
        .flash { border-radius: 10px; padding: .8rem 1rem; font-size: .92rem; margin-bottom: 1rem; font-weight: 600; }
        .flash.ok { background: #ecfdf5; color: #065f46; }
        .flash.bad { background: #fef2f2; color: #991b1b; }
        .hint { color: #94a3b8; font-size: .85rem; text-align: center; margin-top: .8rem; line-height: 1.5; }
        .blocked { background: #fff7ed; color: #7c2d12; border-radius: 10px; padding: 1rem; font-size: .9rem; line-height: 1.55; }
        .blocked code { background: #ffedd5; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1><i class="ri-qr-scan-2-line"></i> Scan a carton</h1>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}">Close</a>
        </div>

        @if(session('success'))
            <div class="flash ok"><i class="ri-check-line"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash bad"><i class="ri-error-warning-line"></i> {{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="flash bad">{{ $errors->first() }}</div>
        @endif

        {{-- Camera --}}
        <div class="card" id="cameraCard">
            <div id="reader"></div>
            <div id="cameraBlocked" class="blocked" style="display:none;"></div>
            <button id="startBtn" style="margin-top:1rem;"><i class="ri-camera-line"></i> Start camera</button>
            <p class="hint">Point the camera at the QR on the carton.</p>
        </div>

        {{-- Filled in once a carton has been read --}}
        <div class="card" id="orderCard" style="display:none;">
            <div class="mark" id="oMark">—</div>
            <div class="sub" id="oSub">—</div>

            <div class="counts" id="oCounts"></div>

            <form method="POST" id="scanForm">
                @csrf
                <input type="hidden" name="scan_key" id="scanKey">

                <label for="stage">What is happening to these cartons?</label>
                <select name="stage" id="stage" required></select>

                <div id="containerWrap" style="display:none;">
                    <label for="container_id">Which container?</label>
                    <select name="container_id" id="container_id"></select>
                </div>

                <div id="warehouseWrap" style="display:none;">
                    <label for="warehouse_id">Into which warehouse?</label>
                    <select name="warehouse_id" id="warehouse_id"></select>
                </div>

                <label for="cartons">How many cartons?</label>
                <input type="number" step="0.01" min="0.01" name="cartons" id="cartons" required>

                <label for="note">Note (optional)</label>
                <input type="text" name="note" id="note" placeholder="e.g. 2 cartons damaged">

                <button type="submit" style="margin-top:1.2rem;"><i class="ri-save-line"></i> Record scan</button>
                <button type="button" class="ghost" id="cancelBtn" style="margin-top:.5rem;">Scan a different carton</button>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reader = document.getElementById('reader');
    const startBtn = document.getElementById('startBtn');
    const blocked = document.getElementById('cameraBlocked');
    const cameraCard = document.getElementById('cameraCard');
    const orderCard = document.getElementById('orderCard');
    const form = document.getElementById('scanForm');
    const stage = document.getElementById('stage');
    let scanner = null;

    function show(el, on) { el.style.display = on ? '' : 'none'; }

    function cameraUnavailable(reason) {
        show(reader, false);
        show(startBtn, false);
        blocked.innerHTML = reason;
        show(blocked, true);
    }

    // The camera is only handed out on a secure connection. Say so plainly —
    // there is no way to record a scan without it.
    if (!window.isSecureContext) {
        cameraUnavailable(
            '<strong>The camera needs a secure connection.</strong><br>' +
            'This page is open over plain <code>http</code>, and browsers only allow camera ' +
            'access over <code>https</code> (or on <code>localhost</code>). Ask your administrator ' +
            'to put the site on https, then scan again.'
        );
    } else if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraUnavailable('<strong>This browser has no camera support.</strong><br>Try Chrome or Safari on a phone.');
    }

    startBtn.addEventListener('click', async function () {
        startBtn.disabled = true;
        startBtn.textContent = 'Starting…';

        scanner = new Html5Qrcode('reader');
        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onDecoded,
                () => {},
            );
            show(startBtn, false);
        } catch (e) {
            cameraUnavailable(
                '<strong>The camera could not be opened.</strong><br>' +
                'Permission may have been denied, or another app is using it. ' +
                'Allow camera access for this site and reload.'
            );
        }
    });

    async function onDecoded(text) {
        // A carton's QR is its tracking link; the token is the last part of it.
        const token = (String(text).match(/\/track\/([A-Za-z0-9]+)/) || [])[1];
        if (!token) { return; }

        await scanner.stop();

        try {
            const response = await fetch('{{ url('scan') }}/' + token, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) { throw new Error((await response.json()).message || 'Unknown code.'); }
            fill(await response.json());
        } catch (e) {
            alert(e.message);
            location.reload();
        }
    }

    function fill(data) {
        form.action = data.record_url;
        document.getElementById('scanKey').value = data.scan_key;
        document.getElementById('oMark').textContent = data.shipping_mark || data.order_no;
        document.getElementById('oSub').textContent =
            data.order_no + ' · ' + (data.customer || '—') + ' · ' + data.status +
            (data.container ? ' · ' + data.container : '');
        document.getElementById('cartons').value = data.total_cartons;

        document.getElementById('oCounts').innerHTML = data.stages.map(s =>
            '<div class="count' + (data.total_cartons > 0 && s.counted >= data.total_cartons ? ' done' : '') + '">' +
            '<div class="n">' + (+s.counted) + ' / ' + (+data.total_cartons) + '</div>' +
            '<div class="l">' + s.label + '</div></div>').join('');

        stage.innerHTML = data.stages.map(s => '<option value="' + s.key + '">' + s.action + '</option>').join('');
        document.getElementById('container_id').innerHTML =
            '<option value="">— select a container —</option>' +
            data.containers.map(c => '<option value="' + c.id + '">' + c.label + '</option>').join('');
        document.getElementById('warehouse_id').innerHTML =
            '<option value="">— the order\'s warehouse —</option>' +
            data.warehouses.map(w => '<option value="' + w.id + '">' + w.label + '</option>').join('');

        toggleExtras();
        show(cameraCard, false);
        show(orderCard, true);
    }

    function toggleExtras() {
        show(document.getElementById('containerWrap'), stage.value === 'container_loaded');
        show(document.getElementById('warehouseWrap'), stage.value === 'bd_warehouse');
    }

    stage.addEventListener('change', toggleExtras);
    document.getElementById('cancelBtn').addEventListener('click', () => location.reload());
});
</script>
</body>
</html>
