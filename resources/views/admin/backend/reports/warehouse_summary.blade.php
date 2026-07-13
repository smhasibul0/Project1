@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Warehouse Operations</h4>
                <small class="text-muted">Inventory, staff &amp; operating spend across every warehouse.</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Warehouse Operations</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">All Warehouses ({{ $rows->count() }})</h5></div>
            <div class="card-body p-0">
                <x-data-table id="whSummaryTable" export-name="warehouse-operations">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th class="text-end">Stock Lots</th>
                                <th class="text-end">Units On Hand</th>
                                <th class="text-end">Staff</th>
                                <th class="text-end">Expenses</th>
                                <th class="text-end">Salaries</th>
                                <th class="text-end">Operating Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                            <tr>
                                <td class="fw-semibold">{{ $r->warehouse->name }}</td>
                                <td class="text-end">{{ $r->lots }}</td>
                                <td class="text-end">{{ number_format($r->on_hand, 2) }}</td>
                                <td class="text-end">{{ $r->staff }}</td>
                                <td class="text-end">৳ {{ number_format($r->expenses, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($r->salaries, 2) }}</td>
                                <td class="text-end fw-semibold">৳ {{ number_format($r->operating, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No warehouses yet.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="4">Totals</td>
                                <td class="text-end">৳ {{ number_format($totals['expenses'], 2) }}</td>
                                <td class="text-end">৳ {{ number_format($totals['salaries'], 2) }}</td>
                                <td class="text-end">৳ {{ number_format($totals['operating'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>
@endsection
