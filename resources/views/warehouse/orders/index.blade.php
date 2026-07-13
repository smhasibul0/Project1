@extends('warehouse.layouts.master')
@section('title', 'Orders to Arrive')
@section('warehouse')

@php
    $statusColor = ['pending' => 'secondary', 'sourcing' => 'secondary', 'at_china_warehouse' => 'info', 'shipped' => 'info', 'at_port' => 'warning', 'at_bd_warehouse' => 'primary', 'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger'];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3">
            <h4 class="fs-18 fw-semibold m-0">Orders to Arrive</h4>
            <small class="text-muted">Orders designated to {{ $warehouse->name }}.</small>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">In Transit ({{ $incoming->count() }})</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="text-muted"><tr><th class="ps-3">Order</th><th>Customer</th><th>Shipment</th><th>Status</th><th>ETA</th><th></th></tr></thead>
                    <tbody>
                        @forelse($incoming as $o)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $o->order_no }}</td>
                            <td>{{ $o->customer->name ?? '—' }}</td>
                            <td>{{ $o->shipment_no ?: '—' }}</td>
                            <td><span class="badge bg-{{ $statusColor[$o->goods_status] ?? 'secondary' }}-subtle text-{{ $statusColor[$o->goods_status] ?? 'secondary' }}">{{ $o->statusLabel() }}</span></td>
                            <td>{{ $o->tentative_receive_date?->format('d M Y') ?: '—' }}</td>
                            <td class="text-end pe-3"><a href="{{ route('warehouse.orders.show', $o->id) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No orders in transit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Arrived ({{ $arrived->count() }})</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="text-muted"><tr><th class="ps-3">Order</th><th>Customer</th><th>Status</th><th>Arrived</th><th></th></tr></thead>
                    <tbody>
                        @forelse($arrived as $o)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $o->order_no }}</td>
                            <td>{{ $o->customer->name ?? '—' }}</td>
                            <td><span class="badge bg-{{ $statusColor[$o->goods_status] ?? 'secondary' }}-subtle text-{{ $statusColor[$o->goods_status] ?? 'secondary' }}">{{ $o->statusLabel() }}</span></td>
                            <td>{{ $o->bd_warehouse_date?->format('d M Y') ?: '—' }}</td>
                            <td class="text-end pe-3"><a href="{{ route('warehouse.orders.show', $o->id) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-eye-line"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No arrived orders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
