@extends('portal.layouts.master')
@section('title', 'Orders')
@section('portal')

@php
    $payPill = ['paid' => 'pill-success', 'partial' => 'pill-warning', 'due' => 'pill-muted'];
@endphp

<div class="pt-head">
    <div>
        <h1>My Orders</h1>
        <div class="sub">Track your shipments and balances.</div>
    </div>
</div>

<div class="pt-card">
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>Order No</th><th>Date</th><th class="num">Items</th><th class="num">Total</th><th class="num">Received</th><th class="num">Due</th><th>Payment</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td><strong>{{ $o->order_no }}</strong></td>
                        <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                        <td class="num">{{ $o->items_count }}</td>
                        <td class="num money">৳{{ number_format($o->total_amount, 2) }}</td>
                        <td class="num money">৳{{ number_format($o->received_amount, 2) }}</td>
                        <td class="num money">৳{{ number_format($o->due_amount, 2) }}</td>
                        <td><span class="pill {{ $payPill[$o->payment_status] ?? 'pill-muted' }}">{{ $o->payment_status }}</span></td>
                        <td><span class="pill pill-info">{{ str_replace('_', ' ', $o->goods_status) }}</span></td>
                        <td class="num"><a href="{{ route('portal.order.show', $o->id) }}" class="pt-btn pt-btn-ghost pt-btn-sm">Track</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="9"><div class="pt-empty"><i class="ri-box-3-line"></i>No orders yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
