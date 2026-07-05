@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Balance Sheet</h4>
                <small class="text-muted">Cash &amp; bank position + receivables (snapshot)</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Balance Sheet</li>
                </ol>
            </div>
        </div>

        <div class="row g-3 mb-1">
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Cash &amp; Bank</small><h4 class="mb-0">৳ {{ number_format($cashBank, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Receivables (order dues)</small><h4 class="mb-0">৳ {{ number_format($receivables, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100 bg-light"><div class="card-body"><small class="text-muted d-block">Total Assets</small><h4 class="mb-0 text-success">৳ {{ number_format($total, 2) }}</h4></div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Cash &amp; Bank by Account Type</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Account</th><th>Type</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                        @forelse($grouped as $type => $accounts)
                            <tr class="table-light"><td colspan="2" class="fw-semibold">{{ $type }}</td><td class="text-end fw-semibold">৳ {{ number_format($accounts->sum(fn ($a) => (float) $a->balance), 2) }}</td></tr>
                            @foreach($accounts as $a)
                            <tr>
                                <td class="ps-4">{{ $a->name }}</td>
                                <td>{{ $a->accountType->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($a->balance, 2) }}</td>
                            </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No active accounts.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold" style="border-top:2px solid #cbd5e1;"><td colspan="2">Cash &amp; Bank Total</td><td class="text-end">৳ {{ number_format($cashBank, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
