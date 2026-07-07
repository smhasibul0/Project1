@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'pending' => '#94a3b8', 'sourcing' => '#f59e0b', 'at_china_warehouse' => '#6366f1',
        'shipped' => '#0ea5e9', 'at_port' => '#8b5cf6', 'at_bd_warehouse' => '#0891b2',
        'delivered' => '#537AEF', 'completed' => '#7c5cf0', 'cancelled' => '#ef4444',
    ];
    $payPill = ['paid' => 'accent', 'partial' => 'warning', 'due' => 'muted'];
    $maxPipe = max(1, collect($pipeline)->max('count') ?? 1);
    $statusKeys = collect(\App\Models\Order::goodsStatuses());
    $delta = function ($pct) {
        $up = $pct >= 0;
        return '<span class="dl '.($up ? 'dl-up' : 'dl-down').'">'.($up ? '+' : '').$pct.'% <i class="ri-arrow-right-'.($up ? 'up' : 'down').'-line"></i></span>';
    };
@endphp

<style>
    .dash { --accent:#537AEF; --accent-soft:#e8eeff; --accent-ink:#3a54c4; }
    .dash .card, .dash .dash-card { border:1px solid #eceff4; border-radius:16px; box-shadow:0 1px 3px rgba(16,24,40,.04); background:#fff; }
    .dash .kpi { padding:1.15rem 1.25rem; }
    .dash .kpi .top { display:flex; align-items:flex-start; justify-content:space-between; }
    .dash .kpi .lbl { color:#64748b; font-weight:600; font-size:.82rem; }
    .dash .kpi .val { font-size:1.9rem; font-weight:800; letter-spacing:-.02em; margin:.35rem 0 .5rem; line-height:1; }
    .dash .kpi .ic { width:40px; height:40px; border-radius:11px; display:grid; place-items:center; font-size:1.2rem; }
    .dash .kpi .sub { color:#94a3b8; font-size:.78rem; }
    .dl { font-weight:700; font-size:.74rem; padding:.12rem .45rem; border-radius:999px; white-space:nowrap; }
    .dl-up { background:var(--accent-soft); color:var(--accent-ink); }
    .dl-down { background:#fee2e2; color:#b91c1c; }
    .ic-amber { background:#fff7ed; color:#ea580c; } .ic-blue { background:#e0f2fe; color:#0284c7; }
    .ic-indigo { background:#eef2ff; color:#4f46e5; } .ic-red { background:#fee2e2; color:#dc2626; }
    .ic-accent { background:var(--accent-soft); color:var(--accent); } .ic-violet { background:#f2edff; color:#7c5cf0; }
    .dash .hd { padding:1.05rem 1.25rem .25rem; display:flex; align-items:center; justify-content:space-between; }
    .dash .hd h5 { font-size:1rem; font-weight:700; margin:0; }
    .dash .hd .mut { color:#94a3b8; font-size:.8rem; }
    .dash .dtable { width:100%; font-size:.84rem; }
    .dash .dtable th { text-transform:uppercase; font-size:.68rem; letter-spacing:.04em; color:#94a3b8; font-weight:700; padding:.7rem 1.25rem; border-bottom:1px solid #eef1f6; text-align:left; }
    .dash .dtable td { padding:.75rem 1.25rem; border-bottom:1px solid #f2f4f8; vertical-align:middle; }
    .dash .dtable tr:last-child td { border-bottom:none; }
    .dash .dtable tr:hover td { background:#fafbfe; }
    .dash .st { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .6rem; border-radius:999px; font-size:.74rem; font-weight:700; text-transform:capitalize; }
    .dash .st::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .st-accent { background:var(--accent-soft); color:var(--accent-ink); } .st-warning { background:#fef3c7; color:#b45309; }
    .st-muted { background:#eef1f6; color:#475569; } .st-info { background:#e0f2fe; color:#0369a1; }
    .pipe-row { display:flex; align-items:center; gap:.7rem; margin-bottom:.85rem; }
    .pipe-row .pl { width:120px; font-size:.8rem; color:#475569; font-weight:600; flex:none; }
    .pipe-row .track { flex:1; height:9px; background:#f1f4f9; border-radius:999px; overflow:hidden; }
    .pipe-row .fill { height:100%; border-radius:999px; }
    .pipe-row .cn { width:34px; text-align:right; font-weight:700; font-size:.82rem; }
    .mini { display:flex; align-items:center; gap:.75rem; padding:.65rem 0; border-bottom:1px solid #f2f4f8; }
    .mini:last-child { border-bottom:none; }
    .mini .mi { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; font-size:1.05rem; flex:none; }
    .mini .mv { font-weight:800; font-size:1.05rem; line-height:1; }
    .mini .ml { color:#94a3b8; font-size:.76rem; font-weight:600; }
    .dash .btn-add { background:var(--accent); border:none; color:#fff; font-weight:600; border-radius:11px; padding:.6rem 1.15rem; }
    .dash .btn-add:hover { filter:brightness(.94); color:#fff; }
</style>

<div class="content dash">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="fs-20 fw-bold m-0">Dashboard</h4>
                <small class="text-muted">Overview of orders, revenue &amp; shipments · {{ now()->format('d M Y') }}</small>
            </div>
            @can('orders.manage')
            <a href="{{ route('orders.create') }}" class="btn btn-add"><i class="ri-add-line me-1"></i> Add Order</a>
            @endcan
        </div>

        {{-- ===== KPI row ===== --}}
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <div class="dash-card kpi h-100">
                    <div class="top"><span class="lbl">Orders (this month)</span><span class="ic ic-amber"><i class="ri-shopping-bag-3-line"></i></span></div>
                    <div class="val">{{ number_format($kpis['orders']['value']) }}</div>
                    <div class="sub">{{ $kpis['orders']['diff'] >= 0 ? '+' : '' }}{{ number_format($kpis['orders']['diff']) }} vs last month {!! $delta($kpis['orders']['pct']) !!}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="dash-card kpi h-100">
                    <div class="top"><span class="lbl">Revenue (this month)</span><span class="ic ic-accent"><i class="ri-money-dollar-circle-line"></i></span></div>
                    <div class="val">৳{{ number_format($kpis['revenue']['value']) }}</div>
                    <div class="sub">{{ $kpis['revenue']['diff'] >= 0 ? '+' : '−' }}৳{{ number_format(abs($kpis['revenue']['diff'])) }} vs last month {!! $delta($kpis['revenue']['pct']) !!}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="dash-card kpi h-100">
                    <div class="top"><span class="lbl">Profit (this month)</span><span class="ic ic-indigo"><i class="ri-line-chart-line"></i></span></div>
                    <div class="val">৳{{ number_format($kpis['profit']['value']) }}</div>
                    <div class="sub">{{ $kpis['profit']['diff'] >= 0 ? '+' : '−' }}৳{{ number_format(abs($kpis['profit']['diff'])) }} vs last month {!! $delta($kpis['profit']['pct']) !!}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="dash-card kpi h-100">
                    <div class="top"><span class="lbl">Total Receivables</span><span class="ic ic-red"><i class="ri-time-line"></i></span></div>
                    <div class="val">৳{{ number_format($kpis['due']['value']) }}</div>
                    <div class="sub">Outstanding dues across all orders</div>
                </div>
            </div>
        </div>

        {{-- ===== Trend + Pipeline ===== --}}
        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="dash-card h-100">
                    <div class="hd"><div><h5>Revenue Trend</h5><span class="mut">Last 6 months</span></div></div>
                    <div class="p-2"><div id="revTrend"></div></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="dash-card h-100">
                    <div class="hd"><div><h5>Order Pipeline</h5><span class="mut">By goods status</span></div></div>
                    <div class="p-3 pt-2">
                        @forelse($pipeline as $p)
                        <div class="pipe-row">
                            <span class="pl">{{ $p['label'] }}</span>
                            <span class="track"><span class="fill" style="width:{{ round($p['count'] / $maxPipe * 100) }}%; background:{{ $statusColors[$statusKeys->search($p['label'])] ?? '#537AEF' }};"></span></span>
                            <span class="cn">{{ $p['count'] }}</span>
                        </div>
                        @empty
                        <div class="text-muted text-center py-4">No orders yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Recent Orders + Side ===== --}}
        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="dash-card h-100">
                    <div class="hd"><div><h5>Recent Orders</h5></div><a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">View all</a></div>
                    <div class="table-responsive mt-2">
                        <table class="dtable">
                            <thead><tr><th>Order No</th><th>Customer</th><th>Date</th><th style="text-align:right;">Total</th><th style="text-align:right;">Due</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($recentOrders as $o)
                                <tr>
                                    <td><a href="{{ route('order.show', $o->id) }}" class="fw-semibold text-dark">{{ $o->order_no }}</a></td>
                                    <td>{{ $o->customer->name ?? '—' }}</td>
                                    <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                                    <td style="text-align:right;">৳{{ number_format($o->total_amount, 2) }}</td>
                                    <td style="text-align:right;">৳{{ number_format($o->due_amount, 2) }}</td>
                                    <td><span class="st st-{{ $payPill[$o->payment_status] ?? 'muted' }}">{{ $o->payment_status }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="dash-card mb-3">
                    <div class="p-3">
                        <div class="mini"><span class="mi ic-indigo"><i class="ri-file-list-3-line"></i></span><div><div class="mv">{{ $side['quotationsPending'] }}</div><div class="ml">Pending quotation requests</div></div></div>
                        <div class="mini"><span class="mi ic-amber"><i class="ri-truck-line"></i></span><div><div class="mv">{{ $side['containersActive'] }}</div><div class="ml">Containers in transit</div></div></div>
                        <div class="mini"><span class="mi ic-blue"><i class="ri-bank-line"></i></span><div><div class="mv">৳{{ number_format($side['cashBank'], 0) }}</div><div class="ml">Cash &amp; bank balance</div></div></div>
                        <div class="mini"><span class="mi ic-accent"><i class="ri-user-3-line"></i></span><div><div class="mv">{{ $side['customers'] }}</div><div class="ml">Total customers</div></div></div>
                    </div>
                </div>
                <div class="dash-card">
                    <div class="hd"><div><h5>Top Customers</h5><span class="mut">By revenue</span></div></div>
                    <div class="p-3 pt-2">
                        @forelse($topCustomers as $tc)
                        <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid #f2f4f8;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="mi ic-accent" style="width:32px;height:32px;font-size:.85rem;">{{ strtoupper(substr($tc->customer->name ?? '?', 0, 1)) }}</span>
                                <div><div class="fw-semibold" style="font-size:.85rem;">{{ $tc->customer->name ?? '—' }}</div><div class="ml">{{ $tc->orders }} orders</div></div>
                            </div>
                            <div class="fw-bold" style="font-size:.85rem;">৳{{ number_format($tc->revenue, 0) }}</div>
                        </div>
                        @empty
                        <div class="text-muted text-center py-3">No data yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') { return; }
    const trend = @json($trend);
    new ApexCharts(document.querySelector('#revTrend'), {
        chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
        series: [{ name: 'Revenue', data: trend.revenue }],
        xaxis: { categories: trend.labels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#94a3b8' } } },
        yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => '৳' + Math.round(v).toLocaleString() } },
        colors: ['#537AEF'],
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .28, opacityTo: .02, stops: [0, 100] } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef1f6', strokeDashArray: 4, padding: { left: 8, right: 8 } },
        tooltip: { y: { formatter: v => '৳' + Number(v).toLocaleString() } },
    }).render();
});
</script>
@endsection
