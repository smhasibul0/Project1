@php
    $stages = \App\Models\OrderScan::STAGES;
    $totalCartons = $order->totalCartons();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track {{ $order->order_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 2rem 1rem; }
        .wrap { max-width: 640px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.05rem; margin: 0 0 .2rem; }
        .muted { color: #64748b; font-size: .9rem; }
        .row { display: flex; justify-content: space-between; padding: .35rem 0; font-size: .9rem; border-bottom: 1px solid #f1f5f9; }
        .row span:first-child { color: #64748b; }
        .badge { background: #e0e7ff; color: #4338ca; border-radius: 99px; padding: .2rem .7rem; font-size: .8rem; font-weight: 600; }
        .timeline { list-style: none; margin: 1rem 0 0; padding: 0; }
        .timeline li { position: relative; padding: 0 0 1.1rem 1.6rem; border-left: 2px solid #e2e8f0; }
        .timeline li:last-child { border-left-color: transparent; padding-bottom: 0; }
        .timeline li::before { content: ''; position: absolute; left: -7px; top: .15rem; width: 12px; height: 12px; border-radius: 50%; background: #7c5cf0; }
        .timeline li:not(:first-child)::before { background: #cbd5e1; }
        .t-status { font-weight: 600; }
        .t-meta { color: #94a3b8; font-size: .8rem; }
        .t-note { font-size: .88rem; margin-top: .15rem; }
        .empty { text-align: center; color: #94a3b8; padding: 1.5rem 0; }

        /* Staff-only scan panel */
        .staff { border-color: #7c5cf0; border-width: 2px; }
        .staff .tag { display: inline-block; background: #7c5cf0; color: #fff; font-size: .7rem; font-weight: 700; letter-spacing: .04em; padding: .15rem .6rem; border-radius: 99px; text-transform: uppercase; margin-bottom: .6rem; }
        .counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .6rem; margin: .9rem 0 1.1rem; }
        .count { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .6rem .7rem; }
        .count .n { font-size: 1.1rem; font-weight: 700; }
        .count .l { font-size: .74rem; color: #64748b; }
        .count.done { background: #ecfdf5; border-color: #a7f3d0; }
        .scan-cta { display: block; background: #7c5cf0; color: #fff; border-radius: 8px; padding: .7rem 1.2rem; font-weight: 700; text-align: center; text-decoration: none; margin-top: 1rem; }
        .scan-cta:hover { background: #6a49e0; }
        .flash { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .7rem .9rem; font-size: .9rem; margin-bottom: 1rem; }
        .scan-log { list-style: none; margin: 1rem 0 0; padding: 0; font-size: .85rem; }
        .scan-log li { padding: .45rem 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; gap: .8rem; }
        .scan-log li:last-child { border-bottom: none; }
        .scan-log .who { color: #94a3b8; font-size: .78rem; }

        /* Carton label */
        .label-card { text-align: center; }
        .label-card .qr svg { width: 200px; height: 200px; display: block; margin: 0 auto; }
        .qr-mark { font-size: 1.8rem; font-weight: 800; letter-spacing: .04em; margin-top: .6rem; word-break: break-all; }
        .qr-order { font-size: .95rem; font-weight: 700; color: #475569; }
        .qr-hint { margin: .7rem 0 0; }
        .print-btn { background: #1e293b; color: #fff; border: 0; border-radius: 8px; padding: .6rem 1.2rem; font-weight: 600; cursor: pointer; margin-top: .9rem; }
        .print-btn:hover { background: #0f172a; }

        /* Printing gives just the label, at a size that fits a carton. */
        @media print {
            body { background: #fff; padding: 0; }
            .card:not(.label-card) { display: none !important; }
            .label-card { border: 2px solid #000; box-shadow: none; margin: 0 auto; max-width: 340px; }
            .print-btn, .qr-hint { display: none; }
            .label-card .qr svg { width: 240px; height: 240px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1><i class="ri-truck-line"></i> Track Your Order</h1>
            <p class="muted">Shipment {{ $order->order_no }} for {{ $order->customer->business_name ?: $order->customer->name ?? 'you' }}.</p>
        </div>

        @if(session('success'))
            <div class="flash"><i class="ri-check-line"></i> {{ session('success') }}</div>
        @endif

        {{-- The carton label. Printed and stuck on every package before handover:
             it is what the warehouses and the port scan the shipment through by. --}}
        <div class="card label-card">
            @php
                $trackQr = preg_replace('/^<\?xml[^>]*\?>\s*/', '', (string) QrCode::format('svg')
                    ->size(320)->margin(1)->errorCorrection('M')->generate(url()->current()));
            @endphp
            <div class="qr">{!! $trackQr !!}</div>
            <div class="qr-mark">{{ $order->shipping_mark ?: $order->order_no }}</div>
            <div class="qr-order">{{ $order->order_no }}</div>
            <p class="muted qr-hint">
                Print this and attach one to each package before handing them over.
                It is how your shipment is tracked at every stop.
            </p>
            <button type="button" class="print-btn" onclick="window.print()">
                <i class="ri-printer-line"></i> Print label
            </button>
        </div>

        {{-- ============ Staff only: how far the cartons have got ============ --}}
        @if($canScan)
        <div class="card staff">
            <span class="tag">Staff</span>
            <h2>{{ $order->order_no }} · {{ $order->shipping_mark ?: 'no shipping mark' }}</h2>
            <p class="muted">
                {{ rtrim(rtrim(number_format($totalCartons, 2), '0'), '.') }} carton(s) expected
                @if($order->containerNumbers()) · container {{ $order->containerNumbers() }}@endif
            </p>

            <div class="counts">
                @foreach($stages as $key => $stage)
                    @php $done = $order->cartonsScannedAt($key); @endphp
                    <div class="count {{ $totalCartons > 0 && $done >= $totalCartons ? 'done' : '' }}">
                        <div class="n">{{ rtrim(rtrim(number_format($done, 2), '0'), '.') }}<span class="muted" style="font-size:.8rem"> / {{ rtrim(rtrim(number_format($totalCartons, 2), '0'), '.') }}</span></div>
                        <div class="l">{{ $stage['label'] }}</div>
                    </div>
                @endforeach
            </div>

            <a class="scan-cta" href="{{ route('scan.index') }}">
                <i class="ri-qr-scan-2-line"></i> Scan a carton to record
            </a>
            <p class="muted" style="margin:.6rem 0 0; font-size:.82rem;">
                Counting is done by reading the QR on the carton with the camera, so what's
                recorded always matches goods somebody is standing in front of.
            </p>

            @if($order->scans->isNotEmpty())
            <ul class="scan-log">
                @foreach($order->scans as $scan)
                <li>
                    <span>
                        <strong>{{ rtrim(rtrim(number_format($scan->cartons, 2), '0'), '.') }}</strong> ctn · {{ $scan->stageLabel() }}
                        @if($scan->container) · {{ $scan->container->container_number ?: $scan->container->container_code }}@endif
                        @if($scan->note)<div class="muted">{{ $scan->note }}</div>@endif
                    </span>
                    <span class="who">{{ $scan->created_at->format('d M, H:i') }}@if($scan->scannedBy)<br>{{ $scan->scannedBy->name }}@endif</span>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif

        {{-- ============ Everyone: the shipment's progress ============ --}}
        <div class="card">
            <div class="row"><span>Order No</span><span><strong>{{ $order->order_no }}</strong></span></div>
            <div class="row"><span>Order Date</span><span>{{ $order->order_date?->format('d M Y') ?: '—' }}</span></div>
            <div class="row"><span>Current Status</span><span class="badge">{{ $order->statusLabel() }}</span></div>
            <div class="row"><span>Delivery</span><span>{{ ucfirst($order->delivery_status) }}</span></div>

            <ul class="timeline">
                @forelse($order->tracking as $t)
                <li>
                    <div class="t-status">{{ \App\Models\Order::goodsStatuses()[$t->status] ?? $t->status }}</div>
                    <div class="t-meta">{{ $t->created_at->format('d M Y, h:i A') }}</div>
                    @if($t->note)<div class="t-note">{{ $t->note }}</div>@endif
                </li>
                @empty
                <li><div class="empty">No tracking updates yet.</div></li>
                @endforelse
            </ul>
        </div>
    </div>

</body>
</html>
