@extends('portal.layouts.master')
@section('title', 'Quotations')
@section('portal')

@php
    $pill = ['draft' => 'pill-muted', 'submitted' => 'pill-info', 'accepted' => 'pill-success', 'rejected' => 'pill-danger', 'converted' => 'pill-brand'];
@endphp

<div class="pt-head">
    <div>
        <h1>My Quotations</h1>
        <div class="sub">Requests you've submitted and the quotes we've sent back.</div>
    </div>
    <a href="{{ route('portal.quotation.create') }}" class="pt-btn pt-btn-primary"><i class="ri-add-line"></i> New Request</a>
</div>

<div class="pt-card">
    <div class="bd flush">
        <div class="pt-scroll">
            <table class="pt-table">
                <thead><tr><th>No</th><th>Requested</th><th class="num">Items</th><th class="num">Quoted Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($quotations as $q)
                    <tr>
                        <td><strong>{{ $q->quotation_no }}</strong></td>
                        <td>{{ $q->query_received_date?->format('d M Y') ?: $q->created_at->format('d M Y') }}</td>
                        <td class="num">{{ $q->items_count }}</td>
                        <td class="num money">{{ (float) $q->grand_total > 0 ? '৳'.number_format($q->grand_total, 2) : '—' }}</td>
                        <td><span class="pill {{ $pill[$q->status] ?? 'pill-muted' }}">{{ $q->status }}</span></td>
                        <td class="num"><a href="{{ route('portal.quotation.show', $q->id) }}" class="pt-btn pt-btn-ghost pt-btn-sm">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="pt-empty"><i class="ri-file-list-3-line"></i>No quotations yet.<br><a href="{{ route('portal.quotation.create') }}" class="pt-btn pt-btn-primary pt-btn-sm mt-2"><i class="ri-add-line"></i> Submit a request</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
