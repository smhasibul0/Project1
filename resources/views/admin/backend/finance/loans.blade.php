@extends('admin.admin_master')
@section('admin')

@php
    $isBorrowed = $direction === 'borrowed';
    $title = $isBorrowed ? 'Money Borrowed' : 'Money Lent';
    $partyLabel = $isBorrowed ? 'Lender' : 'Borrower';
    $partyHint = $isBorrowed ? 'Who the company borrowed from' : 'Who the company lent to';
    $payLabel = $isBorrowed ? 'Repaid' : 'Received';
    $accountHint = $isBorrowed
        ? 'The account the money landed in. Choosing one adds the principal to its balance.'
        : 'The account the money went out of. Choosing one takes the principal off its balance.';
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">{{ $title }}</h4>
                <small class="text-muted">
                    {{ $isBorrowed
                        ? 'Loans taken from banks and individuals, with or without interest.'
                        : 'Money lent out to banks, companies and individuals, with or without interest.' }}
                </small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('loans.index', $isBorrowed ? 'lent' : 'borrowed') }}" class="btn btn-outline-primary">
                    <i class="ri-arrow-left-right-line me-1"></i> {{ $isBorrowed ? 'Money Lent' : 'Money Borrowed' }}
                </a>
                @can('loans.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#loanModal">
                    <i class="ri-add-line me-1"></i> {{ $isBorrowed ? 'Record Borrowing' : 'Record Lending' }}
                </button>
                @endcan
            </div>
        </div>

        {{-- Summary across everything still active --}}
        <div class="row g-3 mb-3">
            @foreach([
                ['Principal', $principalTotal, 'dark', 'ri-bank-card-line'],
                ['Interest to date', $interestTotal, 'warning', 'ri-percent-line'],
                [$payLabel.' so far', $paidTotal, 'success', 'ri-check-double-line'],
                [$isBorrowed ? 'Still owed' : 'Still to collect', $outstandingTotal, 'danger', 'ri-error-warning-line'],
            ] as [$label, $value, $tone, $icon])
            <div class="col-6 col-lg-3">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3">
                        <small class="text-muted d-block"><i class="{{ $icon }} me-1 text-{{ $tone }}"></i>{{ $label }}</small>
                        <h5 class="mb-0 mt-1 fs-17 text-{{ $tone === 'dark' ? 'body' : $tone }}">৳ {{ number_format($value, 2) }}</h5>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($overdueCount > 0)
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
            <i class="ri-alarm-warning-line fs-18"></i>
            <div class="small">
                {{ $overdueCount }} {{ $isBorrowed ? 'borrowing(s)' : 'lending(s)' }} are past their due date and still outstanding.
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-15">{{ $title }}</h5>
                <small class="text-muted">{{ $loans->count() }} record(s)</small>
            </div>
            <div class="card-body p-0">
                <x-data-table id="loansTable" export-name="{{ $direction }}-loans">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Code</th>
                                <th>{{ $partyLabel }}</th>
                                <th data-filter="Type">Type</th>
                                <th>Started</th>
                                <th>Due</th>
                                <th class="text-end">Principal</th>
                                <th data-filter="Interest">Interest</th>
                                <th class="text-end">Interest to date</th>
                                <th class="text-end">{{ $payLabel }}</th>
                                <th class="text-end">Outstanding</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $loan)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewLoan{{ $loan->id }}">
                                                    <i class="ri-eye-line me-1"></i> View
                                                </button>
                                            </li>
                                            @can('loans.edit')
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editLoan{{ $loan->id }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            @endcan
                                            @can('loans.payments.create')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                @if($loan->outstanding() > 0 && $loan->status === 'active')
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#payLoan{{ $loan->id }}">
                                                    <i class="ri-add-circle-line me-1"></i> {{ $isBorrowed ? 'Record Repayment' : 'Record Receipt' }}
                                                </button>
                                                @else
                                                <span class="dropdown-item disabled text-muted"><i class="ri-checkbox-circle-line me-1"></i> Nothing outstanding</span>
                                                @endif
                                            </li>
                                            @endcan
                                            @can('loans.close')
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#closeLoan{{ $loan->id }}">
                                                    <i class="ri-archive-line me-1"></i> Change Status
                                                </button>
                                            </li>
                                            @endcan
                                            @can('loans.delete')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('loan.delete', $loan->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn" data-payments="{{ $loan->payments->count() }}">
                                                        <i class="ri-delete-bin-line me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td class="fw-semibold">{{ $loan->loan_code }}</td>
                                <td>{{ $loan->counterparty }}</td>
                                <td>{{ $loan->counterpartyTypeLabel() }}</td>
                                <td>{{ $loan->start_date?->format('d M Y') }}</td>
                                <td class="@if($loan->isOverdue()) text-danger fw-semibold @endif">
                                    {{ $loan->due_date?->format('d M Y') ?: '—' }}
                                </td>
                                <td class="text-end fw-semibold">৳ {{ number_format($loan->principal, 2) }}</td>
                                <td>{{ $loan->interestLabel() }}</td>
                                <td class="text-end text-warning">৳ {{ number_format($loan->interestToDate(), 2) }}</td>
                                <td class="text-end text-success">৳ {{ number_format($loan->paidTotal(), 2) }}</td>
                                <td class="text-end @if($loan->outstanding() > 0) text-danger fw-semibold @endif">৳ {{ number_format($loan->outstanding(), 2) }}</td>
                                <td>
                                    @if($loan->status === 'written_off')
                                        <span class="badge bg-danger-subtle text-danger">Written off</span>
                                    @elseif($loan->paymentStatus() === 'settled')
                                        <span class="badge bg-success-subtle text-success">Settled</span>
                                    @elseif($loan->paymentStatus() === 'partial')
                                        <span class="badge bg-warning-subtle text-warning">Partly {{ strtolower($payLabel) }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Outstanding</span>
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

{{-- Record a new loan --}}
@can('loans.create')
<div class="modal fade" id="loanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('loan.store', $direction) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isBorrowed ? 'Record Borrowing' : 'Record Lending' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.backend.finance._loan_fields', ['prefix' => 'add', 'loan' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

@foreach($loans as $loan)

{{-- View --}}
<div class="modal fade" id="viewLoan{{ $loan->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $loan->loan_code }} — {{ $loan->counterparty }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><small class="text-muted d-block">{{ $partyLabel }}</small><strong>{{ $loan->counterparty }} <span class="text-muted fw-normal">· {{ $loan->counterpartyTypeLabel() }}</span></strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Started</small><strong>{{ $loan->start_date?->format('d M Y') }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Due</small><strong class="@if($loan->isOverdue()) text-danger @endif">{{ $loan->due_date?->format('d M Y') ?: 'Open-ended' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Principal</small><strong>৳ {{ number_format($loan->principal, 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Interest basis</small><strong>{{ $loan->interestLabel() }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Interest to date</small><strong class="text-warning">৳ {{ number_format($loan->interestToDate(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Total payable</small><strong>৳ {{ number_format($loan->totalPayable(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">{{ $payLabel }}</small><strong class="text-success">৳ {{ number_format($loan->paidTotal(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Outstanding</small><strong class="text-danger">৳ {{ number_format($loan->outstanding(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Account</small><strong>{{ $loan->account->name ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Status</small><strong>{{ $loan->statusLabel() }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Agreement</small><strong>@if($loan->attachment)<a href="{{ asset('upload/loans/'.$loan->attachment) }}" target="_blank">Open</a>@else — @endif</strong></div>
                    @if($loan->note)
                    <div class="col-12"><small class="text-muted d-block">Note</small><strong>{{ $loan->note }}</strong></div>
                    @endif
                </div>

                <h6 class="mb-2">{{ $payLabel }} history</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Account</th><th>Note</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            @forelse($loan->payments->sortByDesc('paid_on') as $payment)
                            <tr>
                                <td>{{ $payment->paid_on?->format('d M Y') }}</td>
                                <td class="text-end fw-semibold">৳ {{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->method ?: '—' }}</td>
                                <td>{{ $payment->paymentAccount->name ?? '—' }}</td>
                                <td>{{ $payment->note ?: '—' }}</td>
                                <td class="text-end">
                                    @can('loans.payments.delete')
                                    <form action="{{ route('loan.payments.delete', [$loan->id, $payment->id]) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-payment-btn"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Nothing {{ strtolower($payLabel) }} yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit --}}
@can('loans.edit')
<div class="modal fade" id="editLoan{{ $loan->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('loan.update', $loan->id) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ $loan->loan_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.backend.finance._loan_fields', ['prefix' => 'edit'.$loan->id, 'loan' => $loan])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- Record a payment --}}
@can('loans.payments.create')
<div class="modal fade" id="payLoan{{ $loan->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loan.payments.store', $loan->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isBorrowed ? 'Record Repayment' : 'Record Receipt' }} — {{ $loan->loan_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        Principal ৳ {{ number_format($loan->principal, 2) }} + interest ৳ {{ number_format($loan->interestToDate(), 2) }}
                        = <strong>৳ {{ number_format($loan->totalPayable(), 2) }}</strong>.
                        Outstanding <strong>৳ {{ number_format($loan->outstanding(), 2) }}</strong>.
                    </div>

                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="{{ $loan->outstanding() }}" class="form-control" name="amount" required>

                    <label class="form-label mt-3">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="paid_on" value="{{ now()->toDateString() }}" required>

                    <label class="form-label mt-3">Method</label>
                    <select class="form-control" name="method">
                        @foreach($paymentMethods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach
                    </select>

                    <label class="form-label mt-3">Payment account</label>
                    <select class="form-control" name="payment_account_id">
                        <option value="">-- None --</option>
                        <x-account-options :accounts="$accounts" />
                    </select>
                    <small class="text-muted">
                        {{ $isBorrowed ? 'The amount comes off this account.' : 'The amount goes onto this account.' }}
                    </small>

                    <label class="form-label mt-3">Note</label>
                    <textarea class="form-control" name="note" rows="1"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- Change status --}}
@can('loans.close')
<div class="modal fade" id="closeLoan{{ $loan->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('loan.close', $loan->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Status — {{ $loan->loan_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-control" name="status" required>
                        <option value="active" @selected($loan->status === 'active')>Active</option>
                        <option value="settled" @selected($loan->status === 'settled')>Settled</option>
                        <option value="written_off" @selected($loan->status === 'written_off')>Written off</option>
                    </select>
                    <small class="text-muted">
                        A loan settles itself once fully {{ strtolower($payLabel) }}. Written off is for money
                        {{ $isBorrowed ? 'forgiven by the lender' : 'that will not be coming back' }}.
                    </small>

                    <label class="form-label mt-3">Note</label>
                    <textarea class="form-control" name="note" rows="2">{{ $loan->note }}</textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    /**
     * A rate belongs to percentage interest and a flat figure to a fixed
     * charge; a loan with no interest needs neither.
     */
    const syncInterest = select => {
        const prefix = select.dataset.prefix;
        document.getElementById(prefix + 'RateWrap').classList.toggle('d-none', select.value !== 'percentage');
        document.getElementById(prefix + 'AmountWrap').classList.toggle('d-none', select.value !== 'fixed');
    };

    document.querySelectorAll('.interest-type').forEach(function (select) {
        syncInterest(select);
        select.addEventListener('change', () => syncInterest(select));
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const payments = parseInt(btn.dataset.payments, 10) || 0;
        Swal.fire({
            title: 'Delete this record?',
            text: payments > 0
                ? payments + ' payment(s) will be deleted too, and every account they touched is put back.'
                : 'The principal is put back on the account it moved.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));

    document.querySelectorAll('.delete-payment-btn').forEach(btn => btn.addEventListener('click', function () {
        Swal.fire({
            title: 'Delete this payment?',
            text: 'The amount is put back on the account it moved.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
