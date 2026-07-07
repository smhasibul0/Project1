@extends('portal.layouts.master')
@section('title', 'Payments')
@section('portal')

@php
    $payPill = ['paid' => 'pill-success', 'partial' => 'pill-warning', 'due' => 'pill-muted'];
@endphp

<div class="pt-head">
    <div>
        <h1>My Payments</h1>
        <div class="sub">What you've paid and what's outstanding.</div>
    </div>
</div>

<div class="pt-stats">
    <div class="pt-stat">
        <div class="ic ic-green"><i class="ri-checkbox-circle-line"></i></div>
        <div><div class="v money">৳{{ number_format($totalPaid, 0) }}</div><div class="l">Total Paid</div></div>
    </div>
    <div class="pt-stat">
        <div class="ic ic-red"><i class="ri-error-warning-line"></i></div>
        <div><div class="v money">৳{{ number_format($totalDue, 0) }}</div><div class="l">Total Due</div></div>
    </div>
</div>

<div class="pt-card">
    <div class="hd"><h3>By Order</h3></div>
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>Order No</th><th>Date</th><th class="num">Total</th><th class="num">Received</th><th class="num">Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td><strong>{{ $o->order_no }}</strong></td>
                        <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                        <td class="num money">৳{{ number_format($o->total_amount, 2) }}</td>
                        <td class="num money text-success">৳{{ number_format($o->received_amount, 2) }}</td>
                        <td class="num money text-danger">৳{{ number_format($o->due_amount, 2) }}</td>
                        <td><span class="pill {{ $payPill[$o->payment_status] ?? 'pill-muted' }}">{{ $o->payment_status }}</span></td>
                        <td class="num"><a href="{{ route('portal.order.show', $o->id) }}" class="pt-btn pt-btn-ghost pt-btn-sm">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="pt-empty"><i class="ri-wallet-3-line"></i>No orders yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
