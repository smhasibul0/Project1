@extends('portal.layouts.master')
@section('title', 'Order ' . $order->order_no)
@section('portal')

@php
    $statusLabels = \App\Models\Order::goodsStatuses();
    $payPill = ['paid' => 'pill-success', 'partial' => 'pill-warning', 'due' => 'pill-muted'];
@endphp

<div class="pt-head">
    <div>
        <h1 class="d-flex align-items-center gap-2">Order {{ $order->order_no }} <span class="pill pill-info">{{ $statusLabels[$order->goods_status] ?? $order->goods_status }}</span></h1>
        <div class="sub">Placed {{ $order->order_date?->format('d M Y') ?: '—' }}</div>
    </div>
    <a href="{{ route('portal.orders') }}" class="pt-btn pt-btn-ghost">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="pt-card h-100">
            <div class="hd"><h3>Summary</h3></div>
            <div class="bd">
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Shipment No</span><strong>{{ $order->shipment_no ?: '—' }}</strong></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Total</span><strong class="money">৳{{ number_format($order->total_amount, 2) }}</strong></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Received</span><span class="money text-success">৳{{ number_format($order->received_amount, 2) }}</span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Due</span><strong class="money text-danger">৳{{ number_format($order->due_amount, 2) }}</strong></div>
                <div class="d-flex justify-content-between py-1 align-items-center"><span class="text-muted">Payment</span><span class="pill {{ $payPill[$order->payment_status] ?? 'pill-muted' }}">{{ $order->payment_status }}</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="pt-card h-100">
            <div class="hd"><h3>Tracking</h3></div>
            <div class="bd">
                <ul class="list-unstyled mb-0" style="position:relative;">
                    @forelse($order->tracking as $t)
                    <li class="d-flex gap-3 pb-3" style="position:relative;">
                        <div style="width:11px;height:11px;border-radius:50%;background:var(--brand);margin-top:5px;flex:none;box-shadow:0 0 0 4px var(--brand-soft);"></div>
                        <div>
                            <div class="fw-semibold">{{ $statusLabels[$t->status] ?? $t->status }}</div>
                            <div class="text-muted small">{{ $t->created_at->format('d M Y, h:i A') }}</div>
                            @if($t->note)<div class="small mt-1">{{ $t->note }}</div>@endif
                        </div>
                    </li>
                    @empty
                    <li class="pt-empty"><i class="ri-truck-line"></i>No tracking updates yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="pt-card mt-3">
    <div class="hd"><h3>Items ({{ $order->items->count() }})</h3></div>
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>#</th><th>HS Code</th><th>Item</th><th class="num">Qty</th><th class="num">CBM</th><th class="num">Rate/CBM</th><th class="num">Total</th></tr></thead>
                <tbody>
                    @foreach($order->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->hs_code ?: '—' }}</td>
                        <td><strong>{{ $item->item_description ?: '—' }}</strong></td>
                        <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                        <td class="num">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                        <td class="num money">৳{{ number_format($order->sell_rate_per_cbm, 2) }}</td>
                        <td class="num money">৳{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="pt-card mt-3">
    <div class="hd"><h3>Payments ({{ $order->payments->count() }})</h3></div>
    <div class="bd flush">
        <table class="pt-table">
            <thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @forelse($order->payments as $p)
                <tr><td>{{ $p->payment_date?->format('d M Y') ?: '—' }}</td><td>{{ $p->method ?: '—' }}</td><td class="num money">৳{{ number_format($p->amount, 2) }}</td></tr>
                @empty
                <tr><td colspan="3"><div class="pt-empty"><i class="ri-wallet-3-line"></i>No payments recorded.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
