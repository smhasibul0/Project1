@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Account Book</h4>
            </div>
            <div class="text-end">
                <a href="{{ route('payment.accounts') }}" class="btn btn-secondary">← Back to Accounts</a>
            </div>
        </div>

        {{-- Top info + filter cards --}}
        <div class="row g-3 mb-3">

            {{-- Account Info Card --}}
            <div class="col-md-5">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <table class="table table-borderless mb-0" style="font-size:.875rem;">
                            <tr>
                                <td class="fw-semibold ps-0" style="width:150px;">Account Name:</td>
                                <td>{{ $account->name }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold ps-0">Account Type:</td>
                                <td>{{ $account->accountType->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold ps-0">Account Number:</td>
                                <td>{{ $account->account_number ?: $account->name }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold ps-0">Balance:</td>
                                <td>৳ {{ number_format($account->balance, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Filters Card --}}
            <div class="col-md-7">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3">
                            <i class="ri-filter-line me-1"></i> Filters:
                        </h6>
                        <form method="GET" action="{{ route('payment.account.book', $account->id) }}" class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label mb-1 fw-semibold" style="font-size:.8rem;">Date Range:</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                                    <input type="text" class="form-control" id="dateRangePicker" name="date_range"
                                           value="{{ request('date_range', \Carbon\Carbon::now()->subDays(6)->format('d-m-Y').' - '.\Carbon\Carbon::now()->format('d-m-Y')) }}"
                                           placeholder="DD-MM-YYYY - DD-MM-YYYY" readonly style="background:#f8fafc;">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label mb-1 fw-semibold" style="font-size:.8rem;">Transaction Type:</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="ri-swap-line"></i></span>
                                    <select class="form-select" name="transaction_type">
                                        <option value="all" {{ request('transaction_type','all') == 'all' ? 'selected' : '' }}>All</option>
                                        <option value="credit" {{ request('transaction_type') == 'credit' ? 'selected' : '' }}>Credit</option>
                                        <option value="debit"  {{ request('transaction_type') == 'debit'  ? 'selected' : '' }}>Debit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="ri-search-line"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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
                            #ab-table { width:100%; min-width:1100px; border-collapse:collapse; font-size:.8rem; margin-bottom:0; }
                            #ab-table thead th { background:#f8fafc; color:#475569; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:.6rem .9rem; white-space:nowrap; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0; position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
                            #ab-table thead th:last-child { border-right:none; }
                            #ab-table thead th:hover { background:#f1f5f9; color:#1e293b; }
                            #ab-table thead th.sort-asc::after { content:' ↑'; color:#6366f1; }
                            #ab-table thead th.sort-desc::after { content:' ↓'; color:#6366f1; }
                            #ab-table tbody tr { border-bottom:1px solid #e2e8f0; transition:background .1s; }
                            #ab-table tbody tr:nth-child(even) { background:#f8fafc; }
                            #ab-table tbody tr:hover { background:#eef2ff; }
                            #ab-table tbody td { padding:.55rem .9rem; color:#334155; border-right:1px solid #e2e8f0; vertical-align:top; }
                            #ab-table tbody td:last-child { border-right:none; }
                            #ab-table tfoot td { background:#f1f5f9; font-weight:700; color:#1e293b; padding:.55rem .9rem; border-top:2px solid #e2e8f0; }
                            .dt-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.8rem 1rem; border-top:1px solid #e2e8f0; background:#fff; }
                            .dt-info { font-size:.75rem; color:#64748b; }
                            .dt-pagination { display:flex; gap:.2rem; }
                            .dt-pagination button { border:1px solid #cbd5e1; background:#fff; color:#475569; font-size:.75rem; padding:.28rem .6rem; border-radius:5px; cursor:pointer; min-width:30px; transition:all .15s; }
                            .dt-pagination button:hover:not(:disabled) { border-color:#6366f1; color:#6366f1; }
                            .dt-pagination button.active { background:#6366f1; border-color:#6366f1; color:#fff; font-weight:600; }
                            .dt-pagination button:disabled { opacity:.35; cursor:default; }
                            .no-results { text-align:center; padding:2.5rem 1rem; color:#94a3b8; font-size:.85rem; }
                            .text-debit  { color:#dc2626; font-weight:600; }
                            .text-credit { color:#16a34a; font-weight:600; }
                        </style>

                        <div class="dt-card">

                            {{-- Toolbar --}}
                            <div class="dt-controls">
                                <div class="dt-controls-left">
                                    Show
                                    <select id="pageSizeSelect">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100" selected>100</option>
                                    </select>
                                    entries
                                    &nbsp;
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="exportCSV()">
                                        <i class="ri-file-text-line me-1"></i>Export CSV
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="exportExcel()">
                                        <i class="ri-file-excel-line me-1"></i>Export Excel
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="window.print()">
                                        <i class="ri-printer-line me-1"></i>Print
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        <i class="ri-eye-line me-1"></i>Column visibility
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        <i class="ri-file-pdf-line me-1"></i>Export PDF
                                    </button>
                                </div>
                                <div class="dt-search-wrap">
                                    <i class="fas fa-search"></i>
                                    <input class="dt-search" id="searchInput" type="text" placeholder="Search …">
                                </div>
                            </div>

                            {{-- Table --}}
                            <div class="dt-scroll-area">
                                <table id="ab-table">
                                    <thead>
                                        <tr>
                                            <th data-col="0">Date</th>
                                            <th data-col="1">Description</th>
                                            <th data-col="2">Payment Method</th>
                                            <th data-col="3">Payment details</th>
                                            <th data-col="4">Note</th>
                                            <th data-col="5">Added By</th>
                                            <th data-col="6">Debit</th>
                                            <th data-col="7">Credit</th>
                                            <th data-col="8">Balance</th>
                                            <th data-col="9" data-sortable="false">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody">
                                        @forelse($transactions as $txn)
                                        <tr>
                                            <td style="white-space:nowrap;">
                                                {{ \Carbon\Carbon::parse($txn->created_at)->format('d-m-Y') }}<br>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($txn->created_at)->format('h:i A') }}</small>
                                            </td>
                                            <td>
                                                <div>{{ $txn->description ?? ucfirst($txn->type) }}</div>
                                                @if($txn->reference_label && $txn->reference_value)
                                                    <div><strong>{{ $txn->reference_label }}:</strong> {{ $txn->reference_value }}</div>
                                                @endif
                                                @if($txn->invoice_no)
                                                    <div><strong>Invoice No.:</strong>
                                                        <a href="#" class="text-primary">{{ $txn->invoice_no }}</a>
                                                    </div>
                                                @endif
                                                @if($txn->pay_reference)
                                                    <div><strong>Pay reference no.:</strong> {{ $txn->pay_reference }}</div>
                                                @endif
                                                @if($txn->added_by_label)
                                                    <div><strong>Added By:</strong> {{ $txn->added_by_label }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $txn->payment_method ?? '—' }}</td>
                                            <td>{{ $txn->payment_details ?? '' }}</td>
                                            <td>{{ $txn->note ?? '' }}</td>
                                            <td>{{ $txn->addedBy->name ?? '—' }}</td>
                                            <td class="text-debit">
                                                @if($txn->type === 'debit' || $txn->debit > 0)
                                                    ৳ {{ number_format($txn->debit ?? $txn->amount, 2) }}
                                                @endif
                                            </td>
                                            <td class="text-credit">
                                                @if($txn->type === 'credit' || $txn->credit > 0)
                                                    ৳ {{ number_format($txn->credit ?? $txn->amount, 2) }}
                                                @endif
                                            </td>
                                            <td>৳ {{ number_format($txn->running_balance, 2) }}</td>
                                            <td>
                                                {{-- Optional: view/delete action --}}
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="10" class="no-results">No transactions found for this period.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6" class="text-end pe-3">Totals:</td>
                                            <td class="text-debit">৳ {{ number_format($transactions->sum('debit'), 2) }}</td>
                                            <td class="text-credit">৳ {{ number_format($transactions->sum('credit'), 2) }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Footer --}}
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
    const tbody     = document.getElementById('tableBody');
    const allRows   = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = allRows.length;
    let filtered    = [...allRows];
    let currentPage = 1;
    let pageSize    = 100;
    let sortCol     = -1;
    let sortDir     = 1;

    function cellText(row, col) {
        const cell = row.querySelectorAll('td')[col];
        return cell ? cell.innerText.trim() : '';
    }

    function render() {
        allRows.forEach(r => r.style.display = 'none');
        const start = (currentPage - 1) * pageSize;
        const end   = Math.min(start + pageSize, filtered.length);

        if (filtered.length === 0) {
            if (!document.getElementById('no-results-row')) {
                const tr = document.createElement('tr');
                tr.id = 'no-results-row';
                tr.innerHTML = `<td colspan="10" class="no-results">No matching records found</td>`;
                tbody.appendChild(tr);
            }
            document.getElementById('no-results-row').style.display = '';
        } else {
            const noRes = document.getElementById('no-results-row');
            if (noRes) noRes.style.display = 'none';
            filtered.slice(start, end).forEach(r => r.style.display = '');
        }

        document.getElementById('tableInfo').textContent = filtered.length === 0
            ? 'No entries found'
            : `Showing ${start + 1} to ${end} of ${filtered.length} entries`
              + (filtered.length < totalRows ? ` (filtered from ${totalRows} total)` : '');

        renderPagination();
    }

    function renderPagination() {
        const pag        = document.getElementById('pagination');
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        pag.innerHTML    = '';

        const btn = (label, page, disabled = false, active = false) => {
            const b = document.createElement('button');
            b.innerHTML = label;
            b.disabled  = disabled;
            if (active) b.classList.add('active');
            b.onclick = () => { currentPage = page; render(); };
            return b;
        };

        pag.appendChild(btn('‹', currentPage - 1, currentPage === 1));
        const delta = 2;
        let prev = null;
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                if (prev !== null && i - prev > 1) {
                    const dots = document.createElement('button');
                    dots.textContent = '…'; dots.disabled = true;
                    pag.appendChild(dots);
                }
                pag.appendChild(btn(i, i, false, i === currentPage));
                prev = i;
            }
        }
        pag.appendChild(btn('›', currentPage + 1, currentPage === totalPages));
    }

    document.getElementById('searchInput').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        filtered = allRows.filter(row =>
            Array.from(row.querySelectorAll('td')).some(td => td.innerText.toLowerCase().includes(q))
        );
        currentPage = 1;
        render();
    });

    document.getElementById('pageSizeSelect').addEventListener('change', function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        render();
    });

    document.querySelectorAll('#ab-table thead th').forEach(th => {
        if (th.dataset.sortable === 'false') return;
        th.addEventListener('click', () => {
            const col = parseInt(th.dataset.col);
            sortDir   = (sortCol === col) ? sortDir * -1 : 1;
            sortCol   = col;
            document.querySelectorAll('#ab-table thead th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
            filtered.sort((a, b) => {
                const av = cellText(a, col).replace(/[৳,]/g, '');
                const bv = cellText(b, col).replace(/[৳,]/g, '');
                const an = parseFloat(av), bn = parseFloat(bv);
                if (!isNaN(an) && !isNaN(bn)) return (an - bn) * sortDir;
                return av.localeCompare(bv) * sortDir;
            });
            filtered.forEach(r => tbody.appendChild(r));
            currentPage = 1;
            render();
        });
    });

    render();
});

function exportCSV() {
    const rows = document.querySelectorAll('#ab-table thead tr, #ab-table tbody tr:not([style*="display: none"])');
    const csv  = Array.from(rows).map(r =>
        Array.from(r.querySelectorAll('th,td')).map(c => '"' + c.innerText.replace(/"/g,'""').trim() + '"').join(',')
    ).join('\n');
    const a    = document.createElement('a');
    a.href     = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'account_book.csv';
    a.click();
}

function exportExcel() {
    const table = document.getElementById('ab-table').outerHTML;
    const blob  = new Blob([table], { type:'application/vnd.ms-excel' });
    const a     = document.createElement('a');
    a.href      = URL.createObjectURL(blob);
    a.download  = 'account_book.xls';
    a.click();
}
</script>

@endsection