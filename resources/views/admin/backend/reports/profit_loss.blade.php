@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Profit &amp; Loss</h4>
                <small class="text-muted">Revenue, cost &amp; profit per order (accrual)</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Profit &amp; Loss</li>
                </ol>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.profit-loss') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="from" value="{{ $from }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="to" value="{{ $to }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <select class="form-control" name="customer_id">
                            <option value="">All customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> Filter</button>
                        <a href="{{ route('reports.profit-loss') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary cards --}}
        @php $exchangeText = ($exchangeGainLoss < 0 ? '(৳ '.number_format(abs($exchangeGainLoss), 2).')' : '৳ '.number_format($exchangeGainLoss, 2)); @endphp
        <div class="row g-3 mb-1">
            <div class="col-sm-6 col-lg"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total Revenue</small><h4 class="mb-0">৳ {{ number_format($totals['revenue'], 2) }}</h4></div></div></div>
            <div class="col-sm-6 col-lg"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Gross Profit (orders)</small><h4 class="mb-0 {{ $totals['profit'] < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($totals['profit'], 2) }}</h4></div></div></div>
            <div class="col-sm-6 col-lg"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Operating Expenses</small><h4 class="mb-0 text-danger">৳ {{ number_format($operating['total'], 2) }}</h4></div></div></div>
            <div class="col-sm-6 col-lg"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Exchange Gain / (Loss)</small><h4 class="mb-0 {{ $exchangeGainLoss < 0 ? 'text-danger' : ($exchangeGainLoss > 0 ? 'text-success' : '') }}">{{ $exchangeText }}</h4><small class="text-muted">on dollars sold</small></div></div></div>
            <div class="col-sm-12 col-lg"><div class="card h-100 border-primary"><div class="card-body"><small class="text-muted d-block">Net Profit</small><h4 class="mb-0 {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($netProfit, 2) }}</h4><small class="text-muted">{{ number_format($netMargin, 2) }}% margin</small></div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Orders ({{ $rows->count() }})</h5></div>
            <div class="card-body p-0">
                <x-data-table id="plTable" export-name="profit-loss">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Freight</th>
                                <th class="text-end">Duty &amp; Taxes</th>
                                <th class="text-end">Order Costs</th>
                                <th class="text-end">LC Cost</th>
                                <th class="text-end">Container Cost</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('order.show', $r->order->id) }}">{{ $r->order->order_no }}</a></td>
                                <td>{{ $r->order->order_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $r->order->customer->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($r->revenue, 2) }}</td>
                                <td class="text-end">{{ number_format($r->freight_cost, 2) }}</td>
                                <td class="text-end">{{ number_format($r->duty_total, 2) }}</td>
                                <td class="text-end">{{ number_format($r->order_cost, 2) }}</td>
                                <td class="text-end">{{ number_format($r->lc_cost, 2) }}</td>
                                <td class="text-end">{{ number_format($r->container_cost, 2) }}</td>
                                <td class="text-end {{ $r->profit < 0 ? 'text-danger' : '' }}">{{ number_format($r->profit, 2) }}</td>
                                <td class="text-end">{{ number_format($r->margin, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No orders in this range.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="3">Totals</td>
                                <td class="text-end">{{ number_format($totals['revenue'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['freight_cost'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['duty_total'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['order_cost'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['lc_cost'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['container_cost'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['profit'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['margin'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </x-data-table>
            </div>
        </div>

        {{-- Operating expenses → Net Profit --}}
        <div class="row justify-content-end">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Order Profit → Net Profit</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <tbody>
                                <tr><td class="ps-3">Gross profit from orders</td><td class="text-end pe-3">৳ {{ number_format($totals['profit'], 2) }}</td></tr>
                                <tr><td class="ps-3 text-muted">Less: Warehouse expenses</td><td class="text-end pe-3 text-danger">(৳ {{ number_format($operating['warehouse_expenses'], 2) }})</td></tr>
                                <tr><td class="ps-3 text-muted">Less: Office expenses</td><td class="text-end pe-3 text-danger">(৳ {{ number_format($operating['office_expenses'], 2) }})</td></tr>
                                <tr><td class="ps-3 text-muted">Less: Staff salaries</td><td class="text-end pe-3 text-danger">(৳ {{ number_format($operating['salaries'], 2) }})</td></tr>
                                <tr><td class="ps-3 text-muted">Less: Standalone LC charges</td><td class="text-end pe-3 text-danger">(৳ {{ number_format($operating['standalone_lc'], 2) }})</td></tr>
                                <tr><td class="ps-3 text-muted">Add: Exchange gain / (loss) on dollars sold</td><td class="text-end pe-3 {{ $exchangeGainLoss < 0 ? 'text-danger' : ($exchangeGainLoss > 0 ? 'text-success' : '') }}">{{ $exchangeText }}</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-semibold">
                                    <td class="ps-3">Net Profit</td>
                                    <td class="text-end pe-3 {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">৳ {{ number_format($netProfit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <small class="text-muted d-block mb-4">Operating expenses are warehouse overheads, office running costs &amp; salaries in the selected date range, across all warehouses. The exchange gain / (loss) is on dollars sold in the range — the taka they fetched against what they cost; LC payments only turn taka into dollars and are never costs.</small>
            </div>
        </div>
    </div>
</div>
@endsection
