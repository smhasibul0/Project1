@extends('warehouse.layouts.master')
@section('title', 'Inventory')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Inventory</h4>
                <small class="text-muted">Goods received into {{ $warehouse->name }}.</small>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="inventoryTable" export-name="inventory">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Item</th>
                                <th>Order</th>
                                <th>Category</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Dispatched</th>
                                <th class="text-end">On Hand</th>
                                <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stocks as $s)
                            <tr>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('warehouse.inventory.show', $s->id) }}" class="btn btn-sm btn-outline-secondary" title="History"><i class="ri-eye-line"></i></a>
                                        @if($s->onHand() > 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary dispatch-btn" title="Dispatch"
                                                data-action="{{ route('warehouse.inventory.dispatch', $s->id) }}"
                                                data-item="{{ $s->item_description }}"
                                                data-max="{{ $s->onHand() }}"><i class="ri-logout-box-r-line"></i></button>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $s->item_description }}</td>
                                <td>{{ $s->order->order_no ?? '—' }}</td>
                                <td>{{ $s->category->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($s->received_qty, 2) }}</td>
                                <td class="text-end">{{ number_format($s->dispatched_qty, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($s->onHand(), 2) }} {{ $s->unit->name ?? '' }}</td>
                                <td>{{ $s->received_date?->format('d M Y') ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

{{-- Dispatch modal --}}
<div class="modal fade" id="dispatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="dispatchForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Dispatch stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" id="dispatchItem"></p>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span id="dispatchMaxLabel" class="text-muted"></span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="quantity" id="dispatchQty" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference (optional)</label>
                        <input type="text" class="form-control" name="reference" placeholder="e.g. Delivered to customer">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Note (optional)</label>
                        <textarea class="form-control" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('warehouse_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = new bootstrap.Modal(document.getElementById('dispatchModal'));
    const form = document.getElementById('dispatchForm');
    const qty = document.getElementById('dispatchQty');
    document.querySelectorAll('.dispatch-btn').forEach(btn => btn.addEventListener('click', function () {
        form.action = btn.dataset.action;
        document.getElementById('dispatchItem').textContent = btn.dataset.item;
        const max = parseFloat(btn.dataset.max);
        qty.max = max;
        qty.value = '';
        document.getElementById('dispatchMaxLabel').textContent = '(max ' + max + ')';
        modal.show();
    }));
});
</script>
@endsection
