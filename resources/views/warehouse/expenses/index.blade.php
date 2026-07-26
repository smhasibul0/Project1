@extends('warehouse.layouts.master')
@section('title', 'Expenses')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Expenses</h4>
                <small class="text-muted">Operating expenses for {{ $warehouse->name }} — total ৳ {{ number_format($total, 2) }}@if($totalDue > 0), due ৳ {{ number_format($totalDue, 2) }}@endif.</small>
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
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Payment Status</th>
                                <th>Note</th>
                                <th class="dt-noexport">Doc</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expenses as $e)
                            <tr>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Payments" data-bs-toggle="modal" data-bs-target="#payModal{{ $e->id }}"><i class="ri-money-dollar-circle-line"></i></button>
                                        <form action="{{ route('warehouse.expenses.delete', $e->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </div>
                                </td>
                                <td>{{ $e->expense_date?->format('d M Y') }}</td>
                                <td>
                                    @if($e->category)
                                        {{ $e->category->parent->name ?? $e->category->name }}@if($e->category->parent) <span class="text-muted">· {{ $e->category->name }}</span>@endif
                                    @else <span class="text-muted">—</span> @endif
                                </td>
                                <td class="text-end fw-semibold">৳ {{ number_format($e->amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($e->paidTotal(), 2) }}</td>
                                <td class="text-end @if($e->dueTotal() > 0) text-danger fw-semibold @endif">৳ {{ number_format($e->dueTotal(), 2) }}</td>
                                <td>
                                    @if($e->paymentStatus() === 'paid') <span class="badge bg-success-subtle text-success">Paid</span>
                                    @elseif($e->paymentStatus() === 'partial') <span class="badge bg-warning-subtle text-warning">Partial</span>
                                    @else <span class="badge bg-danger-subtle text-danger">Due</span>
                                    @endif
                                </td>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('warehouse.expenses.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
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
                            <label class="form-label">Total amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="expenseAmount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expense_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Attachment</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expense note</label>
                            <textarea class="form-control" name="note" rows="1"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Add payment</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="payment_amount" id="paymentAmount" placeholder="0.00">
                            <small class="text-muted">Leave empty to record as unpaid.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Paid on</label>
                            <input type="date" class="form-control" name="paid_on" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment method</label>
                            <select class="form-control" name="payment_method">
                                @foreach($paymentMethods as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment account</label>
                            <select class="form-control" name="payment_account_id">
                                <option value="">-- None --</option>
                                @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} (৳ {{ number_format($a->balance, 2) }})</option>@endforeach
                            </select>
                            <small class="text-muted">Choosing an account deducts the paid amount from its balance.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment note</label>
                            <textarea class="form-control" name="payment_note" rows="1"></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3 fw-semibold">Payment due: ৳ <span id="paymentDue">0.00</span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Per-expense payments modal --}}
@foreach($expenses as $e)
<div class="modal fade" id="payModal{{ $e->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payments — {{ $e->category->name ?? 'Expense' }} ({{ $e->expense_date?->format('d M Y') }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>Total: <strong>৳ {{ number_format($e->amount, 2) }}</strong></span>
                    <span>Paid: <strong>৳ {{ number_format($e->paidTotal(), 2) }}</strong></span>
                    <span>Due: <strong class="@if($e->dueTotal() > 0) text-danger @endif">৳ {{ number_format($e->dueTotal(), 2) }}</strong></span>
                </div>

                @if($e->payments->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Paid on</th><th>Method</th><th>Account</th><th class="text-end">Amount</th><th>Note</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($e->payments as $p)
                            <tr>
                                <td>{{ $p->paid_on?->format('d M Y') }}</td>
                                <td>{{ $p->method ?: '—' }}</td>
                                <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($p->amount, 2) }}</td>
                                <td>{{ $p->note ?: '—' }}</td>
                                <td class="text-end">
                                    <form action="{{ route('warehouse.expenses.payments.delete', [$e->id, $p->id]) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-payment-btn" title="Delete payment"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-3">No payments recorded yet.</p>
                @endif

                @if($e->dueTotal() > 0)
                <form action="{{ route('warehouse.expenses.payments.store', $e->id) }}" method="POST">
                    @csrf
                    <h6 class="mb-3">Add payment</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $e->dueTotal() }}" class="form-control" name="amount" value="{{ $e->dueTotal() }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Paid on <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="paid_on" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment method</label>
                            <select class="form-control" name="method">
                                @foreach($paymentMethods as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment account</label>
                            <select class="form-control" name="payment_account_id">
                                <option value="">-- None --</option>
                                @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} (৳ {{ number_format($a->balance, 2) }})</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment note</label>
                            <textarea class="form-control" name="note" rows="1"></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">Save Payment</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endforeach
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

    // Live "Payment due" figure in the Add Expense modal.
    const totalInput = document.getElementById('expenseAmount');
    const payInput = document.getElementById('paymentAmount');
    const dueLabel = document.getElementById('paymentDue');
    function refreshDue() {
        const due = Math.max(0, (parseFloat(totalInput.value) || 0) - (parseFloat(payInput.value) || 0));
        dueLabel.textContent = due.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    totalInput.addEventListener('input', refreshDue);
    payInput.addEventListener('input', refreshDue);

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this expense?', text: 'All its payments will be reversed back to their accounts.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    document.querySelectorAll('.delete-payment-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this payment?', text: 'The amount will be credited back to its account.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
