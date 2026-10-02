@extends('warehouse.layouts.master')
@section('title', 'Stock Lot')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">{{ $stock->item_description }}</h4>
                <small class="text-muted">{{ $stock->order->order_no ?? 'No order' }} · received {{ $stock->received_date?->format('d M Y') ?: '—' }}</small>
            </div>
            <a href="{{ route('warehouse.inventory.index') }}" class="btn btn-secondary btn-sm">Back</a>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Received</small><strong>{{ number_format($stock->received_qty, 2) }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Dispatched</small><strong>{{ number_format($stock->dispatched_qty, 2) }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">On Hand</small><strong class="text-primary">{{ number_format($stock->onHand(), 2) }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">HS Code</small><strong>{{ $stock->orderItem->hs_code ?? '—' }}</strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Movement History</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="text-muted"><tr><th class="ps-3">Date</th><th>Type</th><th class="text-end">Qty</th><th>Reference</th><th>Note</th><th>By</th></tr></thead>
                    <tbody>
                        @forelse($stock->movements as $m)
                        <tr>
                            <td class="ps-3">{{ $m->moved_date?->format('d M Y') ?: $m->created_at->format('d M Y') }}</td>
                            <td>
                                @if($m->type === 'received')<span class="badge bg-success-subtle text-success">Received</span>
                                @elseif($m->type === 'dispatched')<span class="badge bg-danger-subtle text-danger">Dispatched</span>
                                @else<span class="badge bg-secondary-subtle text-secondary">Adjustment</span>@endif
                            </td>
                            <td class="text-end {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ number_format($m->quantity, 2) }}</td>
                            <td>{{ $m->reference ?: '—' }}</td>
                            <td>{{ $m->note ?: '—' }}</td>
                            <td>{{ $m->movedBy->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No movements.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-activity-history :record="$stock" />
    </div>
</div>
@endsection
