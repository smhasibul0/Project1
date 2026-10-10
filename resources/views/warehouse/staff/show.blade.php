@extends('warehouse.layouts.master')
@section('title', $staff->name)
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">{{ $staff->name }} {!! $staff->is_active ? '' : '<span class="badge bg-secondary ms-1">Inactive</span>' !!}</h4>
                <small class="text-muted">{{ $staff->designation ?: 'Staff' }} · {{ $staff->warehouse->name ?? '' }}</small>
            </div>
            <a href="{{ route('warehouse.staff.index') }}" class="btn btn-secondary btn-sm">Back</a>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Phone</small><strong>{{ $staff->phone ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Email</small><strong>{{ $staff->email ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Joined</small><strong>{{ $staff->join_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Monthly Salary</small><strong>৳ {{ number_format($staff->monthly_salary, 2) }}</strong></div>
                @if($staff->note)<div class="col-12"><small class="text-muted d-block">Note</small>{{ $staff->note }}</div>@endif
            </div>
        </div>

        {{-- Monthly salary summary --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Salary Summary — {{ $year }}</h6>
                <form method="GET" class="d-flex align-items-center gap-2 m-0">
                    <label class="form-label m-0 text-muted">Year</label>
                    <select class="form-control form-control-sm" name="year" onchange="this.form.submit()">
                        @foreach($years as $y)<option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="text-muted">
                            <tr>
                                <th class="ps-3">Month</th>
                                <th class="text-end">Salary</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Status</th>
                                <th>Payments</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summary as $row)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $row['label'] }}</td>
                                <td class="text-end">৳ {{ number_format($row['salary'], 2) }}</td>
                                <td class="text-end">৳ {{ number_format($row['paid'], 2) }}</td>
                                <td class="text-end {{ $row['due'] > 0 ? 'text-danger fw-semibold' : '' }}">৳ {{ number_format($row['due'], 2) }}</td>
                                <td>
                                    @if($row['salary'] <= 0 && $row['paid'] <= 0)
                                        <span class="badge bg-secondary-subtle text-secondary">No salary set</span>
                                    @elseif($row['status'] === 'paid')
                                        <span class="badge bg-success-subtle text-success">Paid</span>
                                    @elseif($row['status'] === 'partial')
                                        <span class="badge bg-warning-subtle text-warning">Partial</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Due</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($row['payments'] as $p)
                                        <div><small>{{ $p->payment_date?->format('d M') }} — ৳ {{ number_format($p->amount, 2) }}{{ $p->paymentAccount ? ' · '.$p->paymentAccount->name : '' }}</small></div>
                                    @empty
                                        <small class="text-muted">—</small>
                                    @endforelse
                                </td>
                                <td class="text-end pe-3">
                                    @if(($row['due'] > 0 || ($row['salary'] <= 0)) && auth()->user()->can('warehouse.staff.salary.create'))
                                    <button type="button" class="btn btn-sm btn-outline-primary pay-month-btn"
                                            data-month="{{ $row['month'] }}" data-amount="{{ $row['due'] > 0 ? $row['due'] : '' }}"
                                            data-bs-toggle="modal" data-bs-target="#salaryModal">
                                        Pay
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No months to show for {{ $year }}.</td></tr>
                            @endforelse
                        </tbody>
                        @if(count($summary))
                        <tfoot class="fw-semibold">
                            <tr>
                                <td class="ps-3">Total</td>
                                <td class="text-end">৳ {{ number_format(collect($summary)->sum('salary'), 2) }}</td>
                                <td class="text-end">৳ {{ number_format(collect($summary)->sum('paid'), 2) }}</td>
                                <td class="text-end">৳ {{ number_format(collect($summary)->sum('due'), 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Documents --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Documents</h6>
                        @can('warehouse.staff.documents.upload')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#docModal"><i class="ri-upload-2-line me-1"></i> Upload</button>@endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <tbody>
                                @forelse($staff->documents as $d)
                                <tr>
                                    <td class="ps-3"><i class="ri-file-text-line me-1 text-muted"></i>{{ $d->title }}<div><small class="text-muted">{{ $d->created_at->format('d M Y') }}{{ $d->uploadedBy ? ' · '.$d->uploadedBy->name : '' }}</small></div></td>
                                    <td class="text-end pe-3">
                                        <a href="{{ asset('upload/staff_documents/'.$d->file) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ri-download-2-line"></i></a>
                                        @can('warehouse.staff.documents.delete')<form action="{{ route('warehouse.staff.document.delete', [$staff->id, $d->id]) }}" method="POST" class="d-inline m-0">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="ri-delete-bin-line"></i></button></form>@endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td class="text-center text-muted py-4">No documents uploaded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Salary payments --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Salary Payments <small class="text-muted">(paid ৳ {{ number_format($salaryTotal, 2) }})</small></h6>
                        @can('warehouse.staff.salary.create')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#salaryModal"><i class="ri-add-line me-1"></i> Pay Salary</button>@endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="text-muted"><tr><th class="ps-3">Month</th><th class="text-end">Amount</th><th>Paid On</th><th>From</th><th></th></tr></thead>
                            <tbody>
                                @forelse($staff->salaryPayments as $p)
                                <tr>
                                    <td class="ps-3">{{ $p->salary_month }}</td>
                                    <td class="text-end fw-semibold">৳ {{ number_format($p->amount, 2) }}@if($p->dollarNote())<small class="d-block text-muted fw-normal">{{ $p->dollarNote() }}</small>@endif</td>
                                    <td>{{ $p->payment_date?->format('d M Y') }}</td>
                                    <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                                    <td class="text-end pe-3">
                                        @if($p->attachment)<a href="{{ asset('upload/salaries/'.$p->attachment) }}" target="_blank" class="me-1"><i class="ri-attachment-2"></i></a>@endif
                                        @can('warehouse.staff.salary.delete')<form action="{{ route('warehouse.staff.salary.delete', [$staff->id, $p->id]) }}" method="POST" class="d-inline m-0">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="ri-delete-bin-line"></i></button></form>@endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No salary payments yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <x-activity-history :record="$staff" />
    </div>
</div>

{{-- Upload document modal --}}
@can('warehouse.staff.documents.upload')
<div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('warehouse.staff.document.store', $staff->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. National ID, Contract">
                    </div>
                    <div class="mb-1">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="file" required>
                        <small class="text-muted">PDF, image or document, up to 4 MB.</small>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Upload</button></div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- Pay salary modal --}}
@can('warehouse.staff.salary.create')
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('warehouse.staff.salary.store', $staff->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Pay Salary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Salary Month <span class="text-danger">*</span></label>
                        <input type="month" class="form-control" name="salary_month" value="{{ now()->format('Y-m') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" value="{{ $staff->monthly_salary }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="payment_date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Paid From (account)</label>
                        <select class="form-control" name="payment_account_id">
                            <option value="">-- Not from an account --</option>
                            <x-account-options :accounts="$accounts" />
                        </select>
                    </div>
                    <x-currency-choice direction="out" />
                    <div class="col-md-6">
                        <label class="form-label">Attachment</label>
                        <input type="file" class="form-control" name="attachment">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Record Payment</button></div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection

@section('warehouse_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prefill the Pay Salary modal when paying a specific month from the summary.
    const salaryForm = document.querySelector('#salaryModal form');
    document.querySelectorAll('.pay-month-btn').forEach(btn => btn.addEventListener('click', function () {
        salaryForm.querySelector('[name="salary_month"]').value = btn.dataset.month;
        if (btn.dataset.amount) { salaryForm.querySelector('[name="amount"]').value = btn.dataset.amount; }
    }));

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
