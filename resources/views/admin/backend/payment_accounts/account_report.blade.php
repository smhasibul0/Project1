@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Payment Account Report</h4>
            </div>
        </div>

        {{-- Filters Card --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3" style="color:#0ea5e9;">
                    <i class="ri-filter-line me-1"></i> Filters:
                </h6>
                <form method="GET" action="{{ route('payment.account.report') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Account:</label>
                        <select class="form-select" name="account_id">
                            <option value="all">All</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date Range:</label>
                        <input type="text" class="form-control" name="date_range" id="reportDateRange"
                               value="{{ request('date_range', \Carbon\Carbon::now()->startOfYear()->format('d-m-Y').' - '.\Carbon\Carbon::now()->format('d-m-Y')) }}"
                               readonly style="background:#f8fafc;">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Datatable Card --}}
        <div class="row">
            <div class="col-12">
                <div class="card p-0 border-0 shadow-none">
                    <div class="card-body p-0">

                        <style>
                            .dt-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); }
                            .dt-controls { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.85rem 1rem; border-bottom:1px solid #e2e8f0; background:#fff; }
                            .dt-controls-left { display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:#64748b; }
                            .dt-controls-left select { border:1px solid #cbd5e1; border-radius:5px; padding:.25rem .4rem; font-size:.8rem; color:#334155; outline:none; cursor:pointer; }
                            .dt-search-wrap { position:relative; }
                            .dt-search-wrap i { position:absolute; left:.6rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.75rem; pointer-events:none; }
                            .dt-search { border:1px solid #cbd5e1; border-radius:5px; padding:.3rem .7rem .3rem 1.9rem; font-size:.8rem; color:#334155; width:210px; outline:none; transition:border-color .2s; }
                            .dt-search:focus { border-color:#6366f1; }
                            .dt-search::placeholder { color:#94a3b8; }
                            .dt-scroll-area { overflow-x:auto; overflow-y:visible; scrollbar-width:thin; scrollbar-color:#cbd5e1 transparent; }
                            .dt-scroll-area::-webkit-scrollbar { height:5px; }
                            .dt-scroll-area::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:99px; }
                            .dt-scroll-area::-webkit-scrollbar-thumb:hover { background:#6366f1; }
                            #par-table { width:100%; min-width:1000px; border-collapse:collapse; font-size:.8rem; margin-bottom:0; }
                            #par-table thead th { background:#f8fafc; color:#475569; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:.6rem .9rem; white-space:nowrap; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0; position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
                            #par-table thead th:last-child { border-right:none; }
                            #par-table thead th:hover { background:#f1f5f9; color:#1e293b; }
                            #par-table thead th.sort-asc::after { content:' ↑'; color:#6366f1; }
                            #par-table thead th.sort-desc::after { content:' ↓'; color:#6366f1; }
                            #par-table tbody tr { border-bottom:1px solid #e2e8f0; transition:background .1s; }
                            #par-table tbody tr:nth-child(even) { background:#f8fafc; }
                            #par-table tbody tr:hover { background:#eef2ff; }
                            #par-table tbody td { padding:.55rem .9rem; color:#334155; border-right:1px solid #e2e8f0; vertical-align:top; }
                            #par-table tbody td:last-child { border-right:none; }
                            #par-table tfoot td { background:#f1f5f9; font-weight:700; color:#1e293b; padding:.55rem .9rem; border-top:2px solid #e2e8f0; }
                            .dt-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.8rem 1rem; border-top:1px solid #e2e8f0; background:#fff; }
                            .dt-info { font-size:.75rem; color:#64748b; }
                            .dt-pagination { display:flex; gap:.2rem; }
                            .dt-pagination button { border:1px solid #cbd5e1; background:#fff; color:#475569; font-size:.75rem; padding:.28rem .6rem; border-radius:5px; cursor:pointer; min-width:30px; transition:all .15s; }
                            .dt-pagination button:hover:not(:disabled) { border-color:#6366f1; color:#6366f1; }
                            .dt-pagination button.active { background:#6366f1; border-color:#6366f1; color:#fff; font-weight:600; }
                            .dt-pagination button:disabled { opacity:.35; cursor:default; }
                            .no-results { text-align:center; padding:2.5rem 1rem; color:#94a3b8; font-size:.85rem; }
                            .badge-invoice { background:#e0f7fa; color:#0097a7; border:1px solid #b2ebf2; padding:.2rem .55rem; border-radius:20px; font-size:.72rem; font-weight:600; white-space:nowrap; }
                            .btn-link-account { border:1px solid #0ea5e9; color:#0ea5e9; background:transparent; border-radius:20px; font-size:.72rem; padding:.2rem .7rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
                            .btn-link-account:hover { background:#0ea5e9; color:#fff; }
                        </style>

                        <div class="dt-card">
                            <div class="dt-controls">
                                <div class="dt-controls-left">
                                    Show
                                    <select id="pageSizeSelect">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100" selected>100</option>
                                    </select>
                                    entries &nbsp;
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="exportTableCSV('par-table','payment_account_report')"><i class="ri-file-text-line me-1"></i>Export CSV</button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="exportTableExcel('par-table','payment_account_report')"><i class="ri-file-excel-line me-1"></i>Export Excel</button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="ri-eye-line me-1"></i>Column visibility</button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="ri-file-pdf-line me-1"></i>Export PDF</button>
                                </div>
                                <div class="dt-search-wrap">
                                    <i class="fas fa-search"></i>
                                    <input class="dt-search" id="searchInput" type="text" placeholder="Search …">
                                </div>
                            </div>

                            <div class="dt-scroll-area">
                                <table id="par-table">
                                    <thead>
                                        <tr>
                                            <th data-col="0">Date</th>
                                            <th data-col="1">Payment Ref No.</th>
                                            <th data-col="2">Invoice No./Ref. No.</th>
                                            <th data-col="3">Amount</th>
                                            <th data-col="4">Payment Type</th>
                                            <th data-col="5">Account</th>
                                            <th data-col="6">Description</th>
                                            <th data-col="7" data-sortable="false">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody">
                                        @forelse($transactions as $txn)
                                        <tr>
                                            <td style="white-space:nowrap;">
                                                {{ \Carbon\Carbon::parse($txn->created_at)->format('d-m-Y h:i A') }}
                                            </td>
                                            <td>{{ $txn->payment_ref_no ?? '—' }}</td>
                                            <td>
                                                @if($txn->invoice_no)
                                                    <span class="badge-invoice">{{ $txn->invoice_no }}</span>
                                                @endif
                                            </td>
                                            <td>৳ {{ number_format($txn->amount, 2) }}</td>
                                            <td>{{ $txn->payment_type ?? $txn->type ?? '—' }}</td>
                                            <td>{{ optional($txn->account)->name ?? '—' }} - {{ optional(optional($txn->account)->accountType)->name ?? '—' }}</td>
                                            <td>
                                                @if($txn->customer_name)
                                                    <strong>Customer:</strong> {{ $txn->customer_name }}
                                                @endif
                                            </td>
                                            <td>
                                                <button class="btn-link-account" onclick="linkAccount({{ $txn->id }})">
                                                    Link Account
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="no-results">No transactions found for the selected filters.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end pe-3">Total Amount:</td>
                                            <td>৳ {{ number_format($transactions->sum('amount'), 2) }}</td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="dt-footer">
                                <div class="dt-info" id="tableInfo">Loading…</div>
                                <div class="dt-pagination" id="pagination"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    initDatatable('par-table', 'tableBody', 'searchInput', 'pageSizeSelect', 'tableInfo', 'pagination', 100);
});

function linkAccount(id) {
    // Implement link account modal/logic here
    alert('Link Account for transaction #' + id);
}
</script>

@include('admin.payment_accounts._datatable_js')

@endsection