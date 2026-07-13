@extends('warehouse.layouts.master')
@section('title', 'Expenses')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Expenses</h4>
                <small class="text-muted">Operating expenses for {{ $warehouse->name }} — total ৳ {{ number_format($total, 2) }}.</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="ri-add-line me-1"></i> Add Expense</button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="expensesTable" export-name="expenses">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                                <th>Paid From</th>
                                <th>Note</th>
                                <th class="dt-noexport">Doc</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expenses as $e)
                            <tr>
                                <td>
                                    <form action="{{ route('warehouse.expenses.delete', $e->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                                <td>{{ $e->expense_date?->format('d M Y') }}</td>
                                <td>
                                    @if($e->category)
                                        {{ $e->category->parent->name ?? $e->category->name }}@if($e->category->parent) <span class="text-muted">· {{ $e->category->name }}</span>@endif
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td class="text-end fw-semibold">৳ {{ number_format($e->amount, 2) }}</td>
                                <td>{{ $e->paymentAccount->name ?? '—' }}</td>
                                <td>{{ $e->note ?: '—' }}</td>
                                <td>@if($e->attachment)<a href="{{ asset('upload/expenses/'.$e->attachment) }}" target="_blank"><i class="ri-attachment-2"></i></a>@else — @endif</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

{{-- Add Expense modal --}}
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('warehouse.expenses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select class="form-control" id="parentSelect">
                            <option value="">-- Select --</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sub-category</label>
                        <select class="form-control" name="expense_category_id" id="subSelect">
                            <option value="">-- Select category first --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="expense_date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Paid From (account)</label>
                        <select class="form-control" name="payment_account_id">
                            <option value="">-- Not from an account --</option>
                            @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} (৳ {{ number_format($a->balance, 2) }})</option>@endforeach
                        </select>
                        <small class="text-muted">Choosing an account deducts the amount from its balance.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachment</label>
                        <input type="file" class="form-control" name="attachment">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('warehouse_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const children = @json($categories->mapWithKeys(fn ($c) => [$c->id => $c->children->map(fn ($ch) => ['id' => $ch->id, 'name' => $ch->name])]));
    const parentSel = document.getElementById('parentSelect');
    const subSel = document.getElementById('subSelect');

    parentSel.addEventListener('change', function () {
        const pid = parentSel.value;
        subSel.innerHTML = '';
        if (!pid) { subSel.innerHTML = '<option value="">-- Select category first --</option>'; return; }
        // Default option logs against the parent category itself.
        subSel.insertAdjacentHTML('beforeend', '<option value="' + pid + '">(General)</option>');
        (children[pid] || []).forEach(c => subSel.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + c.name + '</option>'));
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this expense?', text: 'Any linked account payment will be reversed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
