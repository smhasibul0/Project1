@extends('admin.admin_master')
@section('admin')

@php
    // Editing and deleting ledger entries are their own permissions; the report itself only
    // needs reports.cash-flow, so the Action column is dropped entirely for read-only roles.
    $user = auth()->user();
    $canEditTransactions = $user?->can('accounts.transactions.edit') ?? false;
    $canDeleteTransactions = $user?->can('accounts.transactions.delete') ?? false;
    $canManageTransactions = $canEditTransactions || $canDeleteTransactions;
    $canOpenAccountBook = $user?->can('accounts.view') ?? false;
    $filtersActive = request()->hasAny(['from', 'to', 'payment_account_id', 'transaction_type', 'source']);
@endphp

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
            <div class="card-body py-2">
                <a class="d-flex align-items-center justify-content-between fw-semibold text-decoration-none py-2"
                   data-bs-toggle="collapse" href="#cashFlowFilters" role="button"
                   aria-expanded="{{ $filtersActive ? 'true' : 'false' }}" aria-controls="cashFlowFilters">
                    <span><i class="ri-filter-3-line me-1"></i> Filters</span>
                    <i class="ri-arrow-down-s-line"></i>
                </a>
                <div class="collapse {{ $filtersActive ? 'show' : '' }}" id="cashFlowFilters">
                    <form method="GET" action="{{ route('reports.cash-flow') }}" class="row g-2 align-items-end pt-2 pb-1">
                        <div class="col-md-2">
                            <label class="form-label">From</label>
                            <input type="date" class="form-control" name="from" value="{{ $from }}">
                        </div>
                        <div class="col-md-2">
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
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-control" name="transaction_type">
                                <option value="">All</option>
                                <option value="credit" @selected($type === 'credit')>Money in (credit)</option>
                                <option value="debit" @selected($type === 'debit')>Money out (debit)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Source</label>
                            <select class="form-control" name="source">
                                <option value="">All sources</option>
                                @foreach($sourceLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($source === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> Filter</button>
                            <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="row g-3 mb-1">
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total In</small><h4 class="mb-0 text-success">৳ {{ number_format($totalIn, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total Out</small><h4 class="mb-0 text-danger">৳ {{ number_format($totalOut, 2) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card h-100 bg-light"><div class="card-body"><small class="text-muted d-block">Net Cash Flow</small><h4 class="mb-0 {{ $net < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($net, 2) }}</h4></div></div></div>
        </div>

        {{-- Ledger --}}
        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="cashFlowTable" export-name="cash_flow">
                    <table class="ct-table" style="min-width:1200px;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Payment Method</th>
                                <th>Payment details</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Account Balance
                                    <i class="ri-information-fill text-primary" title="Balance of that account after this entry"></i>
                                </th>
                                <th class="text-end">Total Balance
                                    <i class="ri-information-fill text-primary" title="Combined balance of every account after this entry"></i>
                                </th>
                                @if($canManageTransactions)
                                    <th class="dt-noexport text-center">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $txn)
                            <tr>
                                <td style="white-space:nowrap; vertical-align:top;">
                                    {{ $txn->created_at?->format('d-m-Y') ?? '—' }}<br>
                                    <small class="text-muted">{{ $txn->created_at?->format('h:i A') }}</small>
                                </td>
                                <td style="vertical-align:top;">
                                    @if($canOpenAccountBook && $txn->account)
                                        <a href="{{ route('payment.account.book', $txn->account->id) }}">{{ $txn->account->name }}</a>
                                    @else
                                        {{ $txn->account->name ?? '—' }}
                                    @endif
                                </td>
                                <td style="vertical-align:top;">
                                    <div class="text-primary">{{ $txn->description ?: ucfirst($txn->type) }}</div>
                                    @if($txn->usd_amount !== null)
                                        <div><strong>Dollars:</strong> ${{ number_format($txn->usd_amount, 2) }} @ {{ rtrim(rtrim(number_format((float) $txn->usd_rate, 4), '0'), '.') }}</div>
                                    @endif
                                    @if($txn->reference)
                                        <div><strong>Reference No:</strong> {{ $txn->reference }}</div>
                                    @endif
                                    @if($txn->note)
                                        <div class="text-muted">{{ $txn->note }}</div>
                                    @endif
                                    @if($txn->addedBy)
                                        <div><strong>Added By:</strong> {{ $txn->addedBy->name }}</div>
                                    @endif
                                </td>
                                <td style="vertical-align:top;">{{ $txn->payment_method ?: '' }}</td>
                                <td style="vertical-align:top;">{{ $txn->payment_details ?: '' }}</td>
                                <td class="text-end text-danger fw-semibold" style="vertical-align:top;">
                                    @if((float) $txn->debit > 0) ৳ {{ number_format($txn->debit, 2) }} @endif
                                </td>
                                <td class="text-end text-success fw-semibold" style="vertical-align:top;">
                                    @if((float) $txn->credit > 0) ৳ {{ number_format($txn->credit, 2) }} @endif
                                </td>
                                <td class="text-end" style="vertical-align:top;">৳ {{ number_format($txn->running_balance, 2) }}</td>
                                <td class="text-end" style="vertical-align:top;">
                                    {{ $txn->total_balance === null ? '—' : '৳ '.number_format($txn->total_balance, 2) }}
                                </td>
                                @if($canManageTransactions)
                                    <td class="text-center" style="vertical-align:top;">
                                        @if($txn->isManual())
                                            <div class="d-flex gap-1 justify-content-center">
                                                @if($canEditTransactions)
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-txn-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editTransactionModal"
                                                        data-id="{{ $txn->id }}"
                                                        data-source="{{ $txn->source }}"
                                                        data-description="{{ $txn->description }}"
                                                        data-amount="{{ $txn->amount > 0 ? $txn->amount : ($txn->credit + $txn->debit) }}"
                                                        data-date="{{ $txn->created_at?->format('Y-m-d') }}"
                                                        data-payment-method="{{ $txn->payment_method }}"
                                                        data-from-account-id="{{ $txn->from_account_id ?? '' }}"
                                                        data-to-account-id="{{ $txn->to_account_id ?? '' }}"
                                                        data-note="{{ $txn->note }}"
                                                        title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                @endif
                                                @if($canDeleteTransactions)
                                                <form action="{{ route('transaction.delete', $txn->id) }}" method="POST" class="delete-txn-form m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-txn-btn" title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted" title="System entry — edit it where it was created">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                            @empty
                            <tr><td colspan="{{ $canManageTransactions ? 10 : 9 }}" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="5" class="text-end">Totals</td>
                                <td class="text-end text-danger">৳ {{ number_format($totalOut, 2) }}</td>
                                <td class="text-end text-success">৳ {{ number_format($totalIn, 2) }}</td>
                                <td colspan="{{ $canManageTransactions ? 3 : 2 }}"></td>
                            </tr>
                        </tfoot>
                    </table>
                </x-data-table>
            </div>
        </div>

        {{-- Breakdown by source --}}
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
                            <td class="text-end text-danger">{{ $r->out > 0 ? '৳ '.number_format($r->out, 2) : '—' }}@if($r->usd_out > 0)<small class="d-block text-muted">${{ number_format($r->usd_out, 2) }} sent</small>@endif</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-semibold"><td>Total</td><td class="text-end text-success">৳ {{ number_format($totalIn, 2) }}</td><td class="text-end text-danger">৳ {{ number_format($totalOut, 2) }}@if($totalUsdOut > 0)<small class="d-block text-muted fw-normal">${{ number_format($totalUsdOut, 2) }} sent</small>@endif</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@if($canEditTransactions)
    <x-transaction-edit-modal :accounts="$accounts" />
@endif

@endsection
