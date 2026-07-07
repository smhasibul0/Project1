@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = ['draft' => 'secondary', 'requested' => 'warning', 'quoted' => 'info', 'accepted' => 'success', 'negotiating' => 'primary', 'rejected' => 'danger', 'converted' => 'dark'];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Quotation Requests</h4>
                <small class="text-muted">Requests submitted by customers from the portal</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
                    <li class="breadcrumb-item active">Requests</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Requests ({{ $quotations->count() }})</h5></div>
            <div class="card-body p-0">
                <x-data-table id="reqTable" export-name="quotation-requests">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Quotation No</th>
                                <th>Customer</th>
                                <th>Requested</th>
                                <th class="text-end">Items</th>
                                <th class="text-end">Quoted Total</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quotations as $q)
                            <tr>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('quotation.show', $q->id) }}" class="btn btn-sm btn-outline-secondary py-0" title="View"><i class="ri-eye-line"></i></a>
                                        <a href="{{ route('quotation.edit', $q->id) }}" class="btn btn-sm btn-outline-primary py-0"><i class="ri-price-tag-3-line me-1"></i>Quote</a>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $q->quotation_no }}</span></td>
                                <td>{{ $q->customer->name ?? '—' }}</td>
                                <td>{{ $q->query_received_date?->format('d M Y') ?: $q->created_at->format('d M Y') }}</td>
                                <td class="text-end">{{ $q->items_count }}</td>
                                <td class="text-end">{{ (float) $q->grand_total > 0 ? '৳'.number_format($q->grand_total, 2) : '—' }}</td>
                                <td><span class="badge bg-{{ $statusColors[$q->status] ?? 'secondary' }}">{{ $q->statusLabel() }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No customer requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#reqTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, { popperConfig: c => Object.assign({}, c, { strategy: 'fixed' }) });
    });
});
</script>
@endsection
