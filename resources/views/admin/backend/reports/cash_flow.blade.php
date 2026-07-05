@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Cash Flow</h4>
                <small class="text-muted">Money in vs out from the account ledger</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Cash Flow</li>
                </ol>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.cash-flow') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="from" value="{{ $from }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="to" value="{{ $to }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Account</label>
                        <select class="form-control" name="payment_account_id">
                            <option value="">All accounts</option>
                            @foreach($accounts as $a)
                                <option value="{{ $a->id }}" @selected($accountId == $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> Filter</button>
                        <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="row g-3 mb-1">
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total In</small><h4 class="mb-0 text-success">৳ {{ number_format($totalIn, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total Out</small><h4 class="mb-0 text-danger">৳ {{ number_format($totalOut, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100 bg-light"><div class="card-body"><small class="text-muted d-block">Net Cash Flow</small><h4 class="mb-0 {{ $net < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($net, 2) }}</h4></div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Breakdown by source</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Source</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->source }}</td>
                            <td class="text-end text-success">{{ $r->in > 0 ? '৳ '.number_format($r->in, 2) : '—' }}</td>
                            <td class="text-end text-danger">{{ $r->out > 0 ? '৳ '.number_format($r->out, 2) : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-semibold"><td>Total</td><td class="text-end text-success">৳ {{ number_format($totalIn, 2) }}</td><td class="text-end text-danger">৳ {{ number_format($totalOut, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
