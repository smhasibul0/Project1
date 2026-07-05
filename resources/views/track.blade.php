<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Your Order</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 2rem 1rem; }
        .wrap { max-width: 640px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        .muted { color: #64748b; font-size: .9rem; }
        form { display: flex; gap: .5rem; margin-top: 1rem; }
        input { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: .6rem .9rem; font-size: .95rem; outline: none; }
        input:focus { border-color: #7c5cf0; }
        button { background: #7c5cf0; color: #fff; border: 0; border-radius: 8px; padding: .6rem 1.2rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #6a49e0; }
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
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1><i class="ri-truck-line"></i> Track Your Order</h1>
            <p class="muted">Enter your order number to see its current status.</p>
            <form method="GET" action="{{ route('order.track') }}">
                <input type="text" name="order_no" value="{{ $orderNo }}" placeholder="e.g. OR0001" required>
                <button type="submit">Track</button>
            </form>
        </div>

        @if($searched && ! $order)
            <div class="card"><div class="empty">No order found with that number.</div></div>
        @elseif($order)
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
        @endif
    </div>
</body>
</html>
