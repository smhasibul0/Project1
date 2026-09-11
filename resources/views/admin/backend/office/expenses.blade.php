@extends('admin.admin_master')
@section('admin')

@php
    $prevMonth = $month->copy()->subMonth()->format('Y-m');
    $nextMonth = $month->copy()->addMonth()->format('Y-m');
    $defaultDate = $month->isSameMonth(now()) ? now()->toDateString() : $month->copy()->startOfMonth()->toDateString();
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Office Expenses</h4>
                <small class="text-muted">Running costs for the office, split into fixed and variable by cost type.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('office.expenses', ['month' => $prevMonth]) }}" class="btn btn-sm btn-outline-secondary" title="Previous month"><i class="ri-arrow-left-s-line"></i></a>
                <form method="GET" action="{{ route('office.expenses') }}" class="m-0">
                    <input type="month" class="form-control form-control-sm" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                </form>
                <a href="{{ route('office.expenses', ['month' => $nextMonth]) }}" class="btn btn-sm btn-outline-secondary" title="Next month"><i class="ri-arrow-right-s-line"></i></a>
                @if($pendingFixed->isNotEmpty())
                <form action="{{ route('office.expense.generate') }}" method="POST" class="m-0 ms-2" id="generateForm">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <button type="button" class="btn btn-outline-primary" id="generateBtn">
                        <i class="ri-repeat-line me-1"></i> Generate {{ $month->format('M Y') }}
                        <span class="badge bg-primary-subtle text-primary ms-1">{{ $pendingFixed->count() }}</span>
                    </button>
                </form>
                @endif
                @if($costTypes->isEmpty())
                <a href="{{ route('office.cost.types') }}" class="btn btn-primary rounded-pill px-4 ms-2">
                    <i class="ri-add-line me-1"></i> Add Cost Type First
                </a>
                @else
                <button class="btn btn-primary rounded-pill px-4 ms-2" data-bs-toggle="modal" data-bs-target="#expenseModal">
                    <i class="ri-add-line me-1"></i> Add Expense
                </button>
                @endif
            </div>
        </div>

        {{-- Month summary --}}
        <div class="row g-3 mb-3">
            @foreach([
                ['Fixed', $fixedTotal, 'primary', 'ri-repeat-line'],
                ['Variable', $variableTotal, 'info', 'ri-shuffle-line'],
                ['Total', $total, 'dark', 'ri-wallet-3-line'],
                ['Paid', $paidTotal, 'success', 'ri-checkbox-circle-line'],
                ['Outstanding', $outstanding, 'danger', 'ri-error-warning-line'],
            ] as [$label, $value, $tone, $icon])
            <div class="col-6 col-lg">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3">
                        <small class="text-muted d-block"><i class="{{ $icon }} me-1 text-{{ $tone }}"></i>{{ $label }}</small>
                        <h5 class="mb-0 mt-1 fs-17 text-{{ $tone === 'dark' ? 'body' : $tone }}">৳ {{ number_format($value, 2) }}</h5>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($costTypes->isEmpty())
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2">
            <i class="ri-error-warning-line fs-18"></i>
            <div class="small">
                No active cost types yet, so there is nothing to book an expense against.
                <a href="{{ route('office.cost.types') }}" class="fw-semibold">Add a cost type</a> — rent, internet, stationery — and file it under a category first.
            </div>
        </div>
        @endif

        @if($pendingFixed->isNotEmpty())
        <div class="alert alert-primary d-flex align-items-center gap-2 py-2">
            <i class="ri-information-line fs-18"></i>
            <div class="small">
                {{ $month->format('F Y') }} has no entry yet for
                <strong>{{ $pendingFixed->pluck('name')->join(', ', ' and ') }}</strong>
                — ৳ {{ number_format($pendingFixedTotal, 2) }} in standard monthly costs.
                Use <strong>Generate {{ $month->format('M Y') }}</strong> to add them, then edit any amount that differs.
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-15">{{ $month->format('F Y') }}</h5>
                <small class="text-muted">{{ $expenses->count() }} expense(s)</small>
            </div>
            <div class="card-body p-0">
                <x-data-table id="officeExpensesTable" export-name="office-expenses">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Date</th>
                                <th data-filter="Cost Type">Cost Type</th>
                                <th data-filter="Category">Category</th>
                                <th data-filter="Sub-category">Sub-category</th>
                                <th data-filter="Nature">Nature</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th data-filter="Payment Status">Payment Status</th>
                                <th>Note</th>
                                <th class="dt-noexport">Doc</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expenses as $e)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewModal{{ $e->id }}">
                                                    <i class="ri-eye-line me-1"></i> View
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editModal{{ $e->id }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                @if($e->dueTotal() > 0)
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addPayModal{{ $e->id }}">
                                                    <i class="ri-add-circle-line me-1"></i> Add Payment
                                                </button>
                                                @else
                                                <span class="dropdown-item disabled text-muted"><i class="ri-checkbox-circle-line me-1"></i> Fully Paid</span>
                                                @endif
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#payModal{{ $e->id }}">
                                                    <i class="ri-money-dollar-circle-line me-1"></i> View Payments
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $e->payments->count() }}</span>
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('office.expense.delete', $e->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn">
                                                        <i class="ri-delete-bin-line me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td>{{ $e->expense_date?->format('d M Y') }}</td>
                                <td>{{ $e->costType->name ?? '—' }}</td>
                                <td>{{ $e->categoryName() ?? '—' }}</td>
                                <td>{{ $e->subCategoryName() ?? '—' }}</td>
                                <td>
                                    @if($e->isFixed())
                                        <span class="badge bg-primary-subtle text-primary">Fixed</span>
                                    @else
                                        <span class="badge bg-info-subtle text-info">Variable</span>
                                    @endif
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
            <form action="{{ route('office.expense.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Office Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cost type <span class="text-danger">*</span></label>
                            <x-cost-type-select :cost-types="$costTypes" :required="true" />
                            <small class="text-muted">Fixed or variable, and the reporting category, both follow the type. <a href="{{ route('office.cost.types') }}">Manage types</a></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="expenseAmount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expense_date" value="{{ $defaultDate }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Attachment</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                        <div class="col-12">
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
                            <input type="date" class="form-control" name="paid_on" value="{{ $defaultDate }}">
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
                                <x-account-options :accounts="$accounts" />
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

{{-- Per-expense modals: view, edit, add payment, view payments --}}
@foreach($expenses as $e)
@php $expenseTitle = ($e->costType->name ?? 'Expense').' ('.$e->expense_date?->format('d M Y').')'; @endphp

{{-- View --}}
<div class="modal fade" id="viewModal{{ $e->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Expense Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><small class="text-muted d-block">Date</small><strong>{{ $e->expense_date?->format('d M Y') }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Cost type</small><strong>{{ $e->costType->name ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Category</small><strong>{{ $e->categoryName() ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Sub-category</small><strong>{{ $e->subCategoryName() ?? '—' }}</strong></div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Nature</small>
                        @if($e->isFixed()) <span class="badge bg-primary-subtle text-primary">Fixed</span>
                        @else <span class="badge bg-info-subtle text-info">Variable</span>
                        @endif
                    </div>
                    <div class="col-md-4"><small class="text-muted d-block">Total</small><strong>৳ {{ number_format($e->amount, 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Paid</small><strong>৳ {{ number_format($e->paidTotal(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Due</small><strong class="@if($e->dueTotal() > 0) text-danger @endif">৳ {{ number_format($e->dueTotal(), 2) }}</strong></div>
                    <div class="col-md-8"><small class="text-muted d-block">Note</small>{{ $e->note ?: '—' }}</div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Attachment</small>
                        @if($e->attachment)<a href="{{ asset('upload/expenses/'.$e->attachment) }}" target="_blank"><i class="ri-attachment-2 me-1"></i>Open</a>@else — @endif
                    </div>
                </div>

                <h6 class="mb-2">Payments</h6>
                @if($e->payments->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Paid on</th><th>Method</th><th>Account</th><th class="text-end">Amount</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach($e->payments as $p)
                            <tr>
                                <td>{{ $p->paid_on?->format('d M Y') }}</td>
                                <td>{{ $p->method ?: '—' }}</td>
                                <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($p->amount, 2) }}</td>
                                <td>{{ $p->note ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No payments recorded yet.</p>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

{{-- Edit --}}
<div class="modal fade" id="editModal{{ $e->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('office.expense.update', $e->id) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cost type <span class="text-danger">*</span></label>
                        {{-- An inactive type stays pickable while an expense still points at it, so editing never silently re-files the cost. --}}
                        @php $typeOptions = $e->costType && ! $costTypes->contains('id', $e->office_cost_type_id)
                            ? $costTypes->concat([$e->costType])
                            : $costTypes; @endphp
                        <x-cost-type-select :cost-types="$typeOptions" :selected="$e->office_cost_type_id" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="{{ max(0.01, $e->paidTotal()) }}" class="form-control" name="amount" value="{{ $e->amount }}" required>
                        @if($e->paidTotal() > 0)<small class="text-muted">Cannot go below ৳ {{ number_format($e->paidTotal(), 2) }} already paid.</small>@endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="expense_date" value="{{ $e->expense_date?->toDateString() }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachment</label>
                        <input type="file" class="form-control" name="attachment">
                        @if($e->attachment)<small class="text-muted">Leave empty to keep the current file.</small>@endif
                    </div>
                    <div class="col-12">
                        <label class="form-label">Expense note</label>
                        <textarea class="form-control" name="note" rows="1">{{ $e->note }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add payment --}}
@if($e->dueTotal() > 0)
<div class="modal fade" id="addPayModal{{ $e->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('office.expense.payments.store', $e->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment — {{ $expenseTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total: <strong>৳ {{ number_format($e->amount, 2) }}</strong></span>
                        <span>Paid: <strong>৳ {{ number_format($e->paidTotal(), 2) }}</strong></span>
                        <span>Due: <strong class="text-danger">৳ {{ number_format($e->dueTotal(), 2) }}</strong></span>
                    </div>
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
                                <x-account-options :accounts="$accounts" />
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment note</label>
                            <textarea class="form-control" name="note" rows="1"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- View payments --}}
<div class="modal fade" id="payModal{{ $e->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payments — {{ $expenseTitle }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>Total: <strong>৳ {{ number_format($e->amount, 2) }}</strong></span>
                    <span>Paid: <strong>৳ {{ number_format($e->paidTotal(), 2) }}</strong></span>
                    <span>Due: <strong class="@if($e->dueTotal() > 0) text-danger @endif">৳ {{ number_format($e->dueTotal(), 2) }}</strong></span>
                </div>

                @if($e->payments->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
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
                                    <form action="{{ route('office.expense.payments.delete', [$e->id, $p->id]) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-payment-btn" title="Delete payment"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No payments recorded yet.</p>
                @endif
            </div>
            <div class="modal-footer">
                @if($e->dueTotal() > 0)
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addPayModal{{ $e->id }}">
                    <i class="ri-add-circle-line me-1"></i> Add Payment
                </button>
                @endif
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Live "still due" readout on the add-expense form.
    const amount = document.getElementById('expenseAmount');
    const paid = document.getElementById('paymentAmount');
    const due = document.getElementById('paymentDue');
    const refreshDue = () => {
        const outstanding = (parseFloat(amount.value) || 0) - (parseFloat(paid.value) || 0);
        due.textContent = Math.max(0, outstanding).toFixed(2);
    };
    [amount, paid].forEach(el => el.addEventListener('input', refreshDue));

    // Fixed Popper strategy so the responsive-table overflow doesn't clip the open menu.
    document.querySelectorAll('#officeExpensesTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Generate {{ $month->format("F Y") }}?',
                html: 'This adds <strong>{{ $pendingFixed->count() }}</strong> fixed cost(s) totalling <strong>৳ {{ number_format($pendingFixedTotal, 2) }}</strong>, all unpaid.<br>Types already booked this month are skipped.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, generate',
            }).then(r => { if (r.isConfirmed) document.getElementById('generateForm').submit(); });
        });
    }

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        Swal.fire({
            title: 'Delete this expense?',
            text: 'Any payments made will be credited back to their accounts.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));

    document.querySelectorAll('.delete-payment-btn').forEach(btn => btn.addEventListener('click', function () {
        Swal.fire({
            title: 'Delete this payment?',
            text: 'The amount will be credited back to its account.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
