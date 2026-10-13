@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Balance Sheet</h4>
                <small class="text-muted">
                    Cash &amp; bank, receivables and borrowings at the end of
                    <strong>{{ $date->format('d M Y') }}</strong>{{ $isToday ? ' (today, so far)' : '' }}
                </small>
            </div>
            <form method="GET" action="{{ route('reports.balance-sheet') }}" class="d-flex flex-wrap align-items-center gap-2 mt-2 mt-sm-0">
                <label class="small text-muted mb-0" for="balanceSheetDate">As of</label>
                <input type="date" class="form-control form-control-sm" style="width:auto" id="balanceSheetDate" name="date"
                       value="{{ $date->toDateString() }}" max="{{ now(config('app.display_timezone'))->toDateString() }}" onchange="this.form.submit()">
                @unless($isToday)
                <a href="{{ route('reports.balance-sheet') }}" class="btn btn-sm btn-outline-secondary">Today</a>
                @endunless
                <a href="{{ route('reports.balance-sheet.pdf', ['date' => $date->toDateString()]) }}" class="btn btn-sm btn-primary">
                    <i class="ri-file-pdf-2-line me-1"></i> Download PDF
                </a>
            </form>
        </div>

        <div class="row g-3 mb-1">
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Cash &amp; Bank</small><h4 class="mb-0">৳ {{ number_format($cashBank, 2) }}</h4>@if($dollarsHeld > 0)<small class="text-muted">incl. ${{ number_format($dollarsHeld, 2) }} held, at cost</small>@endif</div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Receivables (order dues)</small><h4 class="mb-0">৳ {{ number_format($receivables, 2) }}</h4></div></div></div>
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Money Lent Out</small><h4 class="mb-0">৳ {{ number_format($loansReceivable, 2) }}</h4></div></div></div>
            <div class="col-md-3"><div class="card h-100 bg-light"><div class="card-body"><small class="text-muted d-block">Total Assets</small><h4 class="mb-0 text-success">৳ {{ number_format($total, 2) }}</h4></div></div></div>
        </div>

        <div class="row g-3 mb-1">
            <div class="col-md-6"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Money Borrowed (outstanding)</small><h4 class="mb-0 text-danger">৳ {{ number_format($loansPayable, 2) }}</h4></div></div></div>
            <div class="col-md-6"><div class="card h-100 bg-light"><div class="card-body"><small class="text-muted d-block">Net Worth (assets less borrowings)</small><h4 class="mb-0 {{ $netWorth < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($netWorth, 2) }}</h4></div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Cash &amp; Bank by Account Type</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Account</th><th>Type</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                        @forelse($grouped as $type => $accounts)
                            <tr class="table-light"><td colspan="2" class="fw-semibold">{{ $type }}</td><td class="text-end fw-semibold">৳ {{ number_format($accounts->sum(fn ($a) => $a->balance_as_of + $a->usd_cost_as_of), 2) }}</td></tr>
                            @foreach($accounts as $a)
                            <tr>
                                <td class="ps-4">{{ $a->name }}</td>
                                <td>{{ $a->accountType->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($a->balance_as_of + $a->usd_cost_as_of, 2) }}
                                    @if($a->usd_as_of > 0)<small class="d-block text-muted">৳ {{ number_format($a->balance_as_of, 2) }} + ${{ number_format($a->usd_as_of, 2) }} at cost ৳ {{ number_format($a->usd_cost_as_of, 2) }}</small>@endif
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No accounts open on this day.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold" style="border-top:2px solid #cbd5e1;"><td colspan="2">Cash &amp; Bank Total</td><td class="text-end">৳ {{ number_format($cashBank, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="row g-3 mt-0">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Money Borrowed</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Lender</th><th>Due</th><th class="text-end">Outstanding</th></tr></thead>
                            <tbody>
                                @forelse($borrowings as $loan)
                                <tr>
                                    <td>
                                        {{ $loan->counterparty }}
                                        <small class="text-muted d-block">{{ $loan->loan_code }} &middot; {{ $loan->interestLabel() }}</small>
                                    </td>
                                    <td>
                                        {{ $loan->due_date?->format('d M Y') ?? '—' }}
                                        @if($loan->due_date && $loan->due_date->lt($date) && $loan->outstanding_as_of > 0)<span class="badge bg-danger-subtle text-danger">Overdue</span>@endif
                                    </td>
                                    <td class="text-end">৳ {{ number_format($loan->outstanding_as_of, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nothing borrowed.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold" style="border-top:2px solid #cbd5e1;"><td colspan="2">Total Borrowed</td><td class="text-end">৳ {{ number_format($loansPayable, 2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Money Lent Out</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Borrower</th><th>Due</th><th class="text-end">Outstanding</th></tr></thead>
                            <tbody>
                                @forelse($lendings as $loan)
                                <tr>
                                    <td>
                                        {{ $loan->counterparty }}
                                        <small class="text-muted d-block">{{ $loan->loan_code }} &middot; {{ $loan->interestLabel() }}</small>
                                    </td>
                                    <td>
                                        {{ $loan->due_date?->format('d M Y') ?? '—' }}
                                        @if($loan->due_date && $loan->due_date->lt($date) && $loan->outstanding_as_of > 0)<span class="badge bg-danger-subtle text-danger">Overdue</span>@endif
                                    </td>
                                    <td class="text-end">৳ {{ number_format($loan->outstanding_as_of, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Nothing lent out.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold" style="border-top:2px solid #cbd5e1;"><td colspan="2">Total Lent</td><td class="text-end">৳ {{ number_format($loansReceivable, 2) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection