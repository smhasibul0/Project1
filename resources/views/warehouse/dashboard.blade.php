@extends('warehouse.layouts.master')
@section('title', 'Dashboard')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3">
            <h4 class="fs-18 fw-semibold m-0">{{ $warehouse->name }} — Dashboard</h4>
            <small class="text-muted">Inventory & incoming shipments for your warehouse.</small>
        </div>

        {{-- KPI cards --}}
        <div class="row g-3">
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md rounded d-inline-flex align-items-center justify-content-center" style="background:rgba(var(--brand-rgb),.12); color:var(--brand); width:48px; height:48px; font-size:1.4rem;"><i class="ri-box-3-line"></i></span>
                    <div><h4 class="mb-0">{{ number_format($totalOnHand, 2) }}</h4><small class="text-muted">Units on hand</small></div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md rounded d-inline-flex align-items-center justify-content-center" style="background:#e0f2fe; color:#0284c7; width:48px; height:48px; font-size:1.4rem;"><i class="ri-stack-line"></i></span>
                    <div><h4 class="mb-0">{{ $distinctItems }}</h4><small class="text-muted">Stock lots</small></div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md rounded d-inline-flex align-items-center justify-content-center" style="background:#fef3c7; color:#d97706; width:48px; height:48px; font-size:1.4rem;"><i class="ri-truck-line"></i></span>
                    <div><h4 class="mb-0">{{ $incomingCount }}</h4><small class="text-muted">Incoming orders</small></div>
                </div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card"><div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md rounded d-inline-flex align-items-center justify-content-center" style="background:#dcfce7; color:#16a34a; width:48px; height:48px; font-size:1.4rem;"><i class="ri-checkbox-circle-line"></i></span>
                    <div><h4 class="mb-0">{{ $arrivedCount }}</h4><small class="text-muted">Arrived orders</small></div>
                </div></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            {{-- Incoming orders --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Incoming Orders</h6>
                        @can('warehouse.orders.view')<a href="{{ route('warehouse.orders.index') }}" class="btn btn-sm btn-link">View all</a>@endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="text-muted"><tr><th class="ps-3">Order</th><th>Customer</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($incoming as $o)
                                <tr>
                                    <td class="ps-3"><a href="{{ route('warehouse.orders.show', $o->id) }}">{{ $o->order_no }}</a></td>
                                    <td>{{ $o->customer->name ?? '—' }}</td>
                                    <td><span class="badge bg-info-subtle text-info">{{ $o->statusLabel() }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No incoming orders.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent receipts --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Receipts</h6>
                        @can('warehouse.inventory.view')<a href="{{ route('warehouse.inventory.index') }}" class="btn btn-sm btn-link">Inventory</a>@endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="text-muted"><tr><th class="ps-3">Item</th><th>Order</th><th class="text-end pe-3">On hand</th></tr></thead>
                            <tbody>
                                @forelse($recentReceipts as $s)
                                <tr>
                                    <td class="ps-3"><a href="{{ route('warehouse.inventory.show', $s->id) }}">{{ $s->item_description }}</a></td>
                                    <td>{{ $s->order->order_no ?? '—' }}</td>
                                    <td class="text-end pe-3">{{ number_format($s->onHand(), 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No stock received yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
