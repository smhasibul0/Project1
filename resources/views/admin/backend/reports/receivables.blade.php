@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Receivables</h4>
                <small class="text-muted">Outstanding order dues &amp; aging</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Receivables</li>
                </ol>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.receivables') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Customer</label>
                        <select class="form-control" name="customer_id">
                            <option value="">All customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i> Filter</button>
                        <a href="{{ route('reports.receivables') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Aging summary --}}
        <div class="row g-3 mb-1">
            <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">Total Due</small><h4 class="mb-0 text-danger">৳ {{ number_format($totalDue, 2) }}</h4></div></div></div>
            @foreach($buckets as $label => $amount)
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><small class="text-muted d-block">{{ $label }} days</small><h5 class="mb-0">৳ {{ number_format($amount, 2) }}</h5></div></div></div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Orders with dues ({{ $rows->count() }})</h5></div>
            <div class="card-body p-0">
                <x-data-table id="arTable" export-name="receivables">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Due</th>
                                <th data-filter="Payment">Payment</th>
                                <th class="text-end">Age (days)</th>
                                <th data-filter="Aging">Aging</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('order.show', $r->order->id) }}">{{ $r->order->order_no }}</a></td>
                                <td>{{ $r->order->order_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $r->order->customer->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($r->order->total_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($r->order->received_amount, 2) }}</td>
                                <td class="text-end text-danger">{{ number_format($r->order->due_amount, 2) }}</td>
                                <td><span class="badge bg-{{ $r->order->payment_status === 'partial' ? 'warning' : 'secondary' }} text-capitalize">{{ $r->order->payment_status }}</span></td>
                                <td class="text-end">{{ $r->age }}</td>
                                <td>{{ $r->bucket }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No outstanding receivables.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="5">Total Due</td>
                                <td class="text-end text-danger">{{ number_format($totalDue, 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>
@endsection
