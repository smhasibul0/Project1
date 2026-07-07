@extends('portal.layouts.master')
@section('title', 'Dashboard')
@section('portal')

@php $who = auth()->user()->contact->name ?? auth()->user()->first_name; @endphp

<div class="pt-welcome">
    <div>
        <h2>Welcome back, {{ $who }} 👋</h2>
        <p>Here's a quick look at your sourcing &amp; shipments.</p>
    </div>
    <a href="{{ route('portal.quotation.create') }}" class="pt-btn" style="background:#fff;color:var(--brand);"><i class="ri-add-line"></i> New Quotation Request</a>
</div>

<div class="pt-stats">
    <div class="pt-stat">
        <div class="ic ic-blue"><i class="ri-box-3-line"></i></div>
        <div><div class="v">{{ $openOrders }}</div><div class="l">Open Orders</div></div>
    </div>
    <div class="pt-stat">
        <div class="ic ic-red"><i class="ri-money-dollar-circle-line"></i></div>
        <div><div class="v money">৳{{ number_format($totalDue, 0) }}</div><div class="l">Total Due</div></div>
    </div>
    <div class="pt-stat">
        <div class="ic ic-indigo"><i class="ri-file-list-3-line"></i></div>
        <div><div class="v">{{ $quotationCount }}</div><div class="l">Quotations</div></div>
    </div>
</div>

<div class="pt-card">
    <div class="hd">
        <h3>Recent Orders</h3>
        <a href="{{ route('portal.orders') }}" class="pt-btn pt-btn-ghost pt-btn-sm">View all</a>
    </div>
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>Order No</th><th>Date</th><th class="num">Total</th><th class="num">Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($recentOrders as $o)
                    <tr>
                        <td><strong>{{ $o->order_no }}</strong></td>
                        <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                        <td class="num money">৳{{ number_format($o->total_amount, 2) }}</td>
                        <td class="num money">৳{{ number_format($o->due_amount, 2) }}</td>
                        <td><span class="pill pill-info">{{ str_replace('_', ' ', $o->goods_status) }}</span></td>
                        <td class="num"><a href="{{ route('portal.order.show', $o->id) }}" class="pt-btn pt-btn-ghost pt-btn-sm">Track</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="pt-empty"><i class="ri-inbox-line"></i>No orders yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
