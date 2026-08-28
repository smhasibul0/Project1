@extends('portal.layouts.master')
@section('title', 'Quotation ' . $quotation->quotation_no)
@section('portal')

@php
    $pill = ['draft' => 'pill-muted', 'requested' => 'pill-warning', 'quoted' => 'pill-info', 'accepted' => 'pill-success', 'negotiating' => 'pill-brand', 'rejected' => 'pill-danger', 'converted' => 'pill-success'];
    $canRespond = $quotation->status === 'quoted';
@endphp

<div class="pt-head">
    <div>
        <h1 class="d-flex align-items-center gap-2">Quotation {{ $quotation->quotation_no }} <span class="pill {{ $pill[$quotation->status] ?? 'pill-muted' }}">{{ $quotation->statusLabel() }}</span></h1>
        <div class="sub">Requested {{ $quotation->query_received_date?->format('d M Y') ?: $quotation->created_at->format('d M Y') }}</div>
    </div>
    <a href="{{ route('portal.quotations') }}" class="pt-btn pt-btn-ghost">Back</a>
</div>

@if($canRespond)
<div class="pt-card mb-3" style="border-color: var(--brand); border-width: 1.5px;">
    <div class="bd d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="fw-bold" style="font-size:1.02rem;">We've quoted your request 🎉</div>
            <div class="text-muted">Quoted total: <strong class="money" style="color:var(--brand);font-size:1.1rem;">৳{{ number_format($quotation->grand_total, 2) }}</strong></div>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('portal.quotation.accept', $quotation->id) }}" method="POST" class="m-0">@csrf<button class="pt-btn pt-btn-success"><i class="ri-check-line"></i> Accept</button></form>
            <form action="{{ route('portal.quotation.negotiate', $quotation->id) }}" method="POST" class="m-0">@csrf<button class="pt-btn pt-btn-ghost"><i class="ri-chat-3-line"></i> Negotiate</button></form>
        </div>
    </div>
</div>
@elseif($quotation->status === 'requested')
<div class="pt-card mb-3"><div class="bd text-muted"><i class="ri-time-line me-1"></i> Your request has been received. We'll send you a quote shortly.</div></div>
@elseif($quotation->status === 'accepted')
<div class="pt-card mb-3"><div class="bd text-success"><i class="ri-checkbox-circle-line me-1"></i> You've accepted this quote. We'll turn it into an order.</div></div>
@elseif($quotation->status === 'negotiating')
<div class="pt-card mb-3"><div class="bd text-muted"><i class="ri-chat-3-line me-1"></i> We've received your negotiation request and will get back to you.</div></div>
@elseif($quotation->status === 'rejected')
<div class="pt-card mb-3"><div class="bd text-danger"><i class="ri-close-circle-line me-1"></i> This quotation was declined.</div></div>
@elseif($quotation->status === 'converted')
<div class="pt-card mb-3"><div class="bd text-success"><i class="ri-shopping-bag-3-line me-1"></i> This quotation has been converted to an order.</div></div>
@endif

<div class="pt-card">
    <div class="hd"><h3>Items ({{ $quotation->items->count() }})</h3></div>
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>#</th><th>HS Code</th><th>Item</th><th class="num">Qty</th><th class="num">Net Wt</th><th class="num">CBM</th><th class="num">Rate/CBM</th><th class="num">Total</th></tr></thead>
                <tbody>
                    @foreach($quotation->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->hs_code ?: '—' }}</td>
                        <td><strong>{{ $item->description ?: ($item->remarks ?: '—') }}</strong></td>
                        <td class="num">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                        <td class="num">{{ $item->net_weight ? rtrim(rtrim(number_format($item->net_weight, 3), '0'), '.') : '—' }}</td>
                        <td class="num">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                        <td class="num money">{{ (float) $quotation->sell_rate_per_cbm > 0 ? '৳'.number_format($quotation->sell_rate_per_cbm, 2) : '—' }}</td>
                        <td class="num money">{{ (float) $item->line_total > 0 ? '৳'.number_format($item->line_total, 2) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                @if((float) $quotation->grand_total > 0)
                <tfoot><tr><td colspan="7" class="num">Grand Total</td><td class="num money">৳{{ number_format($quotation->grand_total, 2) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@if($quotation->remarks)
<div class="pt-card mt-3"><div class="bd"><div class="text-muted small mb-1 fw-semibold">Notes</div>{{ $quotation->remarks }}</div></div>
@endif
@endsection
