@extends('warehouse.layouts.master')
@section('title', 'Payroll')
@section('warehouse')

@php $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'); @endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Payroll</h4>
                <small class="text-muted">Salary payments for {{ $warehouse->name }} — {{ $monthLabel }}.</small>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label class="form-label m-0 text-muted">Month</label>
                <input type="month" class="form-control" name="month" value="{{ $month }}" onchange="this.form.submit()">
            </form>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card mb-0"><div class="card-body">
                    <small class="text-muted d-block">Monthly Payroll (active staff)</small>
                    <span class="fs-20 fw-semibold">৳ {{ number_format($totalPayroll, 2) }}</span>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card mb-0"><div class="card-body">
                    <small class="text-muted d-block">Paid for {{ $monthLabel }}</small>
                    <span class="fs-20 fw-semibold text-success">৳ {{ number_format($totalPaid, 2) }}</span>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card mb-0"><div class="card-body">
                    <small class="text-muted d-block">Due for {{ $monthLabel }}</small>
                    <span class="fs-20 fw-semibold {{ $totalDue > 0 ? 'text-danger' : '' }}">৳ {{ number_format($totalDue, 2) }}</span>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="payrollTable" export-name="payroll-{{ $month }}">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Staff</th>
                                <th>Designation</th>
                                <th class="text-end">Monthly Salary</th>
                                <th class="text-end">Paid ({{ $month }})</th>
                                <th class="text-end">Due</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staff as $s)
                            @php
                                $paid = $s->paidForMonth($month);
                                $due = $s->dueForMonth($month);
                                $status = $s->salaryStatusForMonth($month);
                            @endphp
                            <tr>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#payrollModal{{ $s->id }}">
                                        <i class="ri-money-dollar-circle-line me-1"></i> Pay / Payments
                                    </button>
                                </td>
                                <td>
                                    <a href="{{ route('warehouse.staff.show', $s->id) }}">{{ $s->name }}</a>
                                    @unless($s->is_active)<span class="badge bg-secondary ms-1">Inactive</span>@endunless
                                </td>
                                <td>{{ $s->designation ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($s->monthly_salary, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($paid, 2) }}</td>
                                <td class="text-end {{ $due > 0 ? 'text-danger fw-semibold' : '' }}">৳ {{ number_format($due, 2) }}</td>
                                <td>
                                    @if((float) $s->monthly_salary <= 0 && $paid <= 0)
                                        <span class="badge bg-secondary-subtle text-secondary">No salary set</span>
                                    @elseif($status === 'paid')
                                        <span class="badge bg-success-subtle text-success">Paid</span>
                                    @elseif($status === 'partial')
                                        <span class="badge bg-warning-subtle text-warning">Partial</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Due</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

{{-- Per-staff month payments modal --}}
@foreach($staff as $s)
@php
    $monthPayments = $s->salaryPayments->where('salary_month', $month);
    $due = $s->dueForMonth($month);
    $canPay = $due > 0 || (float) $s->monthly_salary <= 0;
@endphp
<div class="modal fade" id="payrollModal{{ $s->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $s->name }} — {{ $monthLabel }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>Monthly Salary: <strong>৳ {{ number_format($s->monthly_salary, 2) }}</strong></span>
                    <span>Paid: <strong>৳ {{ number_format($s->paidForMonth($month), 2) }}</strong></span>
                    <span>Due: <strong class="{{ $due > 0 ? 'text-danger' : '' }}">৳ {{ number_format($due, 2) }}</strong></span>
                </div>

                @if($monthPayments->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Paid On</th><th>Account</th><th class="text-end">Amount</th><th>Note</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($monthPayments as $p)
                            <tr>
                                <td>{{ $p->payment_date?->format('d M Y') }}</td>
                                <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($p->amount, 2) }}</td>
                                <td>{{ $p->note ?: '—' }}</td>
                                <td class="text-end">
                                    @if($p->attachment)<a href="{{ asset('upload/salaries/'.$p->attachment) }}" target="_blank" class="me-1"><i class="ri-attachment-2"></i></a>@endif
                                    <form action="{{ route('warehouse.staff.salary.delete', [$s->id, $p->id]) }}" method="POST" class="d-inline m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete payment"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-3">No payments recorded for {{ $monthLabel }}.</p>
                @endif

                @if($canPay)
                <form action="{{ route('warehouse.staff.salary.store', $s->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="salary_month" value="{{ $month }}">
                    <h6 class="mb-3">Add payment</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" @if($due > 0) max="{{ $due }}" @endif class="form-control" name="amount" value="{{ $due > 0 ? $due : '' }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Paid From (account)</label>
                            <select class="form-control" name="payment_account_id">
                                <option value="">-- Not from an account --</option>
                                <x-account-options :accounts="$accounts" />
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Attachment</label>
                            <input type="file" class="form-control" name="attachment">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" name="note" rows="1"></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
                @else
                <div class="alert alert-success mb-0 py-2"><i class="ri-checkbox-circle-line me-1"></i> Salary fully paid for {{ $monthLabel }}.</div>
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
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this payment?', text: 'Any linked account debit will be credited back.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
