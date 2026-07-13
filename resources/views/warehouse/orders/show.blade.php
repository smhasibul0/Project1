@extends('warehouse.layouts.master')
@section('title', 'Order ' . $order->order_no)
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Order {{ $order->order_no }}</h4>
                <small class="text-muted">{{ $order->customer->name ?? '—' }} · <span class="badge bg-info-subtle text-info">{{ $order->statusLabel() }}</span></small>
            </div>
            <a href="{{ route('warehouse.orders.index') }}" class="btn btn-secondary btn-sm">Back</a>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Shipment No</small><strong>{{ $order->shipment_no ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Shipping Mark</small><strong>{{ $order->shipping_mark ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">ETA</small><strong>{{ $order->tentative_receive_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Reached Warehouse</small><strong>{{ $order->bd_warehouse_date?->format('d M Y') ?: '—' }}</strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Items</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="text-muted"><tr><th class="ps-3">Description</th><th>Category</th><th>HS Code</th><th class="text-end">Qty</th><th class="text-end pe-3">Received</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php $received = $order->warehouseStocks->firstWhere('order_item_id', $item->id); @endphp
                        <tr>
                            <td class="ps-3">{{ $item->item_description ?: '—' }}</td>
                            <td>{{ $item->category->name ?? '—' }}</td>
                            <td>{{ $item->hs_code ?: '—' }}</td>
                            <td class="text-end">{{ number_format($item->quantity, 2) }} {{ $item->unit->name ?? '' }}</td>
                            <td class="text-end pe-3">{!! $received ? '<span class="badge bg-success-subtle text-success">In stock</span>' : '<span class="text-muted">—</span>' !!}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Tracking</h6></div>
            <div class="card-body">
                @forelse($order->tracking as $t)
                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-light text-dark">{{ \App\Models\Order::goodsStatuses()[$t->status] ?? $t->status }}</span>
                    <div><small class="text-muted">{{ $t->created_at->format('d M Y, h:i A') }}{{ $t->changedBy ? ' · '.$t->changedBy->name : '' }}</small>@if($t->note)<div>{{ $t->note }}</div>@endif</div>
                </div>
                @empty
                <div class="text-muted">No tracking history.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
