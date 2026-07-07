@props([
    'id' => 'dataTable',
    'exportName' => null,
])
@php $exportName = $exportName ?? $id; @endphp

{{-- Self-contained table toolbar: entries / search / export / print / column visibility /
     sort / pagination. No jQuery/DataTables. Mark a column's <th> with class "dt-noexport"
     to exclude it from sorting and exports (e.g. the Action column). --}}
<style>
    .ct-wrap .ct-filters { padding:.85rem 1.1rem; border-bottom:1px solid #e2e8f0; background:#fff; }
    .ct-wrap .ct-filters-title { color:#5b7c9d; font-weight:600; font-size:.92rem; display:flex; align-items:center; justify-content:space-between; gap:.4rem; cursor:pointer; user-select:none; padding:.4rem .55rem; border-radius:6px; transition:background-color .15s ease, color .15s ease; }
    .ct-wrap .ct-filters-title:hover { background:rgba(var(--brand-rgb, 83, 122, 239), .08); color:var(--brand, #537AEF); }
    .ct-wrap .ct-filters.open .ct-filters-title { color:var(--brand, #537AEF); }
    .ct-wrap .ct-filters-title .ct-caret { transition:transform .2s ease; }
    .ct-wrap .ct-filters.open .ct-filters-title .ct-caret { transform:rotate(180deg); }
    .ct-wrap .ct-filters-body { padding-top:.9rem; }
    .ct-wrap .ct-filter-label { font-size:.78rem; font-weight:600; color:#334155; margin-bottom:.25rem; display:block; }
    .ct-wrap .ct-filters select { border:1px solid #cbd5e1; border-radius:6px; font-size:.82rem; }
    .ct-wrap .ct-controls { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.85rem 1rem; border-bottom:1px solid #e2e8f0; }
    .ct-wrap .ct-controls-left { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; font-size:.82rem; color:#64748b; }
    .ct-wrap .ct-controls-left select { border:1px solid #cbd5e1; border-radius:6px; padding:.3rem .5rem; font-size:.82rem; color:#334155; outline:none; cursor:pointer; }
    .ct-wrap .ct-btn { border:1px solid rgba(var(--brand-rgb, 83, 122, 239), .4); background:#fff; color:var(--brand, #537AEF); border-radius:6px; padding:.38rem .8rem; font-size:.82rem; font-weight:500; display:inline-flex; align-items:center; gap:.35rem; cursor:pointer; transition:background-color .15s ease,color .15s ease,border-color .15s ease; white-space:nowrap; }
    .ct-wrap .ct-btn:hover, .ct-wrap .ct-btn:focus { background:var(--brand, #537AEF); border-color:var(--brand, #537AEF); color:#fff; }
    .ct-wrap .ct-search-wrap { position:relative; }
    .ct-wrap .ct-search-wrap i { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.85rem; pointer-events:none; }
    .ct-wrap .ct-search { border:1px solid rgba(var(--brand-rgb, 83, 122, 239), .4); border-radius:6px; padding:.42rem .8rem .42rem 2.1rem; font-size:.82rem; color:#334155; width:230px; outline:none; transition:border-color .2s; }
    .ct-wrap .ct-search:focus { border-color:var(--brand, #537AEF); }
    .ct-wrap .ct-search::placeholder { color:#94a3b8; }
    .ct-wrap .ct-scroll-area { overflow-x:auto; scrollbar-width:thin; scrollbar-color:#cbd5e1 transparent; }
    .ct-wrap .ct-scroll-area::-webkit-scrollbar { height:6px; }
    .ct-wrap .ct-scroll-area::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:99px; }
    .ct-wrap table.ct-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-bottom:0; }
    .ct-wrap table.ct-table thead th { background:#f8fafc; color:#475569; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.6rem .9rem; white-space:nowrap; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0; user-select:none; }
    .ct-wrap table.ct-table thead th:last-child { border-right:none; }
    .ct-wrap table.ct-table thead th.ct-sortable { cursor:pointer; }
    .ct-wrap table.ct-table thead th.ct-sortable:hover { background:#f1f5f9; color:#1e293b; }
    .ct-wrap table.ct-table thead th.sort-asc::after { content:' ↑'; color:var(--brand, #537AEF); }
    .ct-wrap table.ct-table thead th.sort-desc::after { content:' ↓'; color:var(--brand, #537AEF); }
    .ct-wrap table.ct-table tbody tr { border-bottom:1px solid #e2e8f0; }
    .ct-wrap table.ct-table tbody tr:nth-child(even) { background:#fbfaff; }
    .ct-wrap table.ct-table tbody tr:hover { background:rgba(var(--brand-rgb, 83, 122, 239), .08); }
    .ct-wrap table.ct-table tbody td { padding:.55rem .9rem; color:#334155; border-right:1px solid #e2e8f0; vertical-align:middle; }
    .ct-wrap table.ct-table tbody td:last-child { border-right:none; }
    .ct-wrap .ct-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.8rem 1rem; border-top:1px solid #e2e8f0; }
    .ct-wrap .ct-info { font-size:.78rem; color:#64748b; }
    .ct-wrap .ct-pagination { display:flex; gap:.25rem; flex-wrap:wrap; }
    .ct-wrap .ct-pagination button { border:1px solid #cbd5e1; background:#fff; color:#475569; font-size:.78rem; padding:.3rem .65rem; border-radius:6px; cursor:pointer; min-width:32px; transition:all .15s; }
    .ct-wrap .ct-pagination button:hover:not(:disabled) { border-color:var(--brand, #537AEF); color:var(--brand, #537AEF); }
    .ct-wrap .ct-pagination button.active { background:var(--brand, #537AEF); border-color:var(--brand, #537AEF); color:#fff; font-weight:600; }
    .ct-wrap .ct-pagination button:disabled { opacity:.35; cursor:default; }
    .ct-wrap .ct-no-results { text-align:center; padding:2.5rem 1rem; color:#94a3b8; font-size:.85rem; }
</style>

<div class="ct-wrap" id="{{ $id }}_wrap">
    <div class="ct-filters" style="display:none;">
        <div class="ct-filters-title">
            <span><i class="ri-filter-3-line"></i> Filters</span>
            <i class="ri-arrow-down-s-line ct-caret"></i>
        </div>
        <div class="ct-filters-body row g-3" style="display:none;"></div>
    </div>
    <div class="ct-controls">
        <div class="ct-controls-left">
            Show
            <select class="ct-page-size">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="all">All</option>
            </select>
            entries
            <button type="button" class="ct-btn ms-1 ct-export-csv"><i class="ri-file-text-line"></i> Export CSV</button>
            <button type="button" class="ct-btn ct-export-excel"><i class="ri-file-excel-2-line"></i> Export Excel</button>
            <button type="button" class="ct-btn ct-print"><i class="ri-printer-line"></i> Print</button>
            <div class="dropdown d-inline-block">
                <button class="ct-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="ri-eye-line"></i> Column visibility</button>
                <ul class="dropdown-menu p-1 ct-colvis" style="max-height:320px; overflow:auto;"></ul>
            </div>
            <button type="button" class="ct-btn ct-export-pdf"><i class="ri-file-pdf-2-line"></i> Export PDF</button>
        </div>
        <div class="ct-search-wrap">
            <i class="ri-search-line"></i>
            <input type="text" class="ct-search" placeholder="Search ...">
        </div>
    </div>

    <div class="ct-scroll-area">
        {{ $slot }}
    </div>

    <div class="ct-footer">
        <div class="ct-info"></div>
        <div class="ct-pagination"></div>
    </div>
</div>

<script>
(function () {
    const wrap = document.getElementById('{{ $id }}_wrap');
    if (!wrap || wrap.dataset.ctReady) { return; }
    wrap.dataset.ctReady = '1';

    const table = wrap.querySelector('table');
    const tbody = table ? table.querySelector('tbody') : null;
    if (!table || !tbody) { return; }

    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = allRows.length;
    const headers = Array.from(table.querySelectorAll('thead th'));
    const colCount = headers.length;
    const excluded = i => headers[i].classList.contains('dt-noexport') || headers[i].classList.contains('ct-hidden');

    let filtered = allRows.slice();
    let currentPage = 1;
    let pageSize = 100;
    let sortCol = -1, sortDir = 1;

    const infoEl = wrap.querySelector('.ct-info');
    const pagEl = wrap.querySelector('.ct-pagination');
    const cellText = (row, col) => { const c = row.querySelectorAll('td')[col]; return c ? c.innerText.trim() : ''; };

    function render() {
        allRows.forEach(r => r.style.display = 'none');
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, filtered.length);
        let noRes = wrap.querySelector('.ct-no-results-row');
        if (filtered.length === 0) {
            if (!noRes) {
                noRes = document.createElement('tr');
                noRes.className = 'ct-no-results-row';
                noRes.innerHTML = '<td colspan="' + colCount + '" class="ct-no-results">No matching records found</td>';
                tbody.appendChild(noRes);
            }
            noRes.style.display = '';
        } else {
            if (noRes) { noRes.style.display = 'none'; }
            filtered.slice(start, end).forEach(r => r.style.display = '');
        }
        infoEl.textContent = filtered.length === 0 ? 'No entries found'
            : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + filtered.length + ' entries'
              + (filtered.length < totalRows ? ' (filtered from ' + totalRows + ' total)' : '');
        renderPagination();
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        pagEl.innerHTML = '';
        const btn = (label, page, disabled, active) => {
            const b = document.createElement('button');
            b.innerHTML = label; b.disabled = !!disabled;
            if (active) { b.classList.add('active'); }
            b.onclick = () => { currentPage = page; render(); };
            return b;
        };
        pagEl.appendChild(btn('‹', currentPage - 1, currentPage === 1, false));
        const delta = 2; let prev = null;
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                if (prev !== null && i - prev > 1) { const d = document.createElement('button'); d.textContent = '…'; d.disabled = true; pagEl.appendChild(d); }
                pagEl.appendChild(btn(i, i, false, i === currentPage));
                prev = i;
            }
        }
        pagEl.appendChild(btn('›', currentPage + 1, currentPage === totalPages, false));
    }

    // ---- Combined search + column filters ----
    let searchQuery = '';
    const columnFilters = {}; // colIndex -> selected value

    function compute() {
        filtered = allRows.filter(function (row) {
            if (searchQuery && !Array.from(row.querySelectorAll('td')).some(td => td.innerText.toLowerCase().includes(searchQuery))) { return false; }
            for (const col in columnFilters) {
                if (cellText(row, parseInt(col, 10)) !== columnFilters[col]) { return false; }
            }
            return true;
        });
        currentPage = 1;
        render();
    }

    wrap.querySelector('.ct-search').addEventListener('input', function () {
        searchQuery = this.value.toLowerCase();
        compute();
    });

    // Build a dropdown filter for every <th data-filter> (distinct column values).
    const filtersBody = wrap.querySelector('.ct-filters-body');
    let hasFilter = false;
    headers.forEach(function (th, idx) {
        if (!th.hasAttribute('data-filter')) { return; }
        hasFilter = true;
        const label = th.getAttribute('data-filter') || th.innerText.trim();
        const values = Array.from(new Set(allRows.map(r => cellText(r, idx)).filter(v => v !== ''))).sort((a, b) => a.localeCompare(b));
        const col = document.createElement('div');
        col.className = 'col-lg-3 col-md-4 col-sm-6';
        col.innerHTML = '<label class="ct-filter-label">' + label + ':</label>'
            + '<select class="form-select form-select-sm"><option value="">All</option>'
            + values.map(v => '<option value="' + v.replace(/"/g, '&quot;') + '">' + v + '</option>').join('')
            + '</select>';
        col.querySelector('select').addEventListener('change', function () {
            if (this.value === '') { delete columnFilters[idx]; } else { columnFilters[idx] = this.value; }
            compute();
        });
        filtersBody.appendChild(col);
    });
    if (hasFilter) {
        const filtersEl = wrap.querySelector('.ct-filters');
        filtersEl.style.display = '';
        // Collapsible: click the "Filters" header to reveal/hide the options.
        wrap.querySelector('.ct-filters-title').addEventListener('click', function () {
            const willOpen = filtersBody.style.display === 'none';
            filtersBody.style.display = willOpen ? '' : 'none';
            filtersEl.classList.toggle('open', willOpen);
        });
    }

    wrap.querySelector('.ct-page-size').addEventListener('change', function () {
        pageSize = this.value === 'all' ? Number.MAX_SAFE_INTEGER : parseInt(this.value, 10);
        currentPage = 1; render();
    });

    headers.forEach(function (th, idx) {
        if (th.classList.contains('dt-noexport')) { return; }
        th.classList.add('ct-sortable');
        th.addEventListener('click', function () {
            sortDir = (sortCol === idx) ? -sortDir : 1;
            sortCol = idx;
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
            filtered.sort(function (a, b) {
                const av = cellText(a, idx).replace(/[৳,]/g, ''), bv = cellText(b, idx).replace(/[৳,]/g, '');
                const an = parseFloat(av), bn = parseFloat(bv);
                if (!isNaN(an) && !isNaN(bn)) { return (an - bn) * sortDir; }
                return av.localeCompare(bv) * sortDir;
            });
            filtered.forEach(r => tbody.appendChild(r));
            currentPage = 1; render();
        });
    });

    const cols = () => headers.map((th, i) => i).filter(i => !excluded(i));
    function matrix() {
        const c = cols();
        return {
            head: c.map(i => headers[i].innerText.trim()),
            body: filtered.map(row => { const cells = row.querySelectorAll('td'); return c.map(i => cells[i] ? cells[i].innerText.trim().replace(/\s+/g, ' ') : ''); }),
        };
    }
    const fname = @json($exportName);
    wrap.querySelector('.ct-export-csv').addEventListener('click', function () {
        const m = matrix();
        const csv = [m.head].concat(m.body).map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv); a.download = fname + '.csv'; a.click();
    });
    wrap.querySelector('.ct-export-excel').addEventListener('click', function () {
        const m = matrix();
        let html = '<table border="1"><thead><tr>' + m.head.map(h => '<th>' + h + '</th>').join('') + '</tr></thead><tbody>';
        html += m.body.map(r => '<tr>' + r.map(c => '<td>' + c + '</td>').join('') + '</tr>').join('') + '</tbody></table>';
        const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' });
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = fname + '.xls'; a.click();
    });
    wrap.querySelector('.ct-print').addEventListener('click', () => window.print());
    wrap.querySelector('.ct-export-pdf').addEventListener('click', () => window.print());

    const menu = wrap.querySelector('.ct-colvis');
    menu.addEventListener('click', e => e.stopPropagation());
    headers.forEach(function (th, idx) {
        if (th.classList.contains('dt-noexport')) { return; }
        const li = document.createElement('li');
        li.innerHTML = '<label class="dropdown-item mb-0" style="cursor:pointer;"><input type="checkbox" checked class="form-check-input me-2"> ' + th.innerText.trim() + '</label>';
        li.querySelector('input').addEventListener('change', function () {
            const show = this.checked;
            th.classList.toggle('ct-hidden', !show);
            th.style.display = show ? '' : 'none';
            allRows.forEach(function (row) { const cell = row.querySelectorAll('td')[idx]; if (cell) { cell.style.display = show ? '' : 'none'; } });
        });
        menu.appendChild(li);
    });

    render();
})();
</script>
