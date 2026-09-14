@extends('admin.admin_master')
@section('admin')

@php $qc = ['draft' => 'secondary', 'requested' => 'warning', 'quoted' => 'info', 'accepted' => 'success', 'negotiating' => 'primary', 'rejected' => 'danger', 'converted' => 'dark']; @endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Quotations</h4>
                <small class="text-muted">Customer product queries &amp; quotes</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Quotations</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Quotation List</h5>
                @can('quotations.create')
                <a href="{{ route('quotations.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Quotation
                </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <x-data-table id="quotationsTable" export-name="quotations">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Quotation No</th>
                                <th>Query Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th class="text-end">Grand Total</th>
                                <th class="text-end">Total Profit</th>
                                <th class="text-end">Margin</th>
                                <th data-filter="Submitted">Submitted</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotations as $q)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="{{ route('quotation.show', $q->id) }}"><i class="ri-eye-line me-2"></i>View</a></li>
                                            @can('quotations.print')
                                            <li><a class="dropdown-item" href="{{ route('quotation.print', $q->id) }}" target="_blank"><i class="ri-file-pdf-2-line me-2"></i>Export PDF</a></li>
                                            @endcan
                                            @can('quotations.edit')
                                            <li><a class="dropdown-item" href="{{ route('quotation.edit', $q->id) }}"><i class="ri-edit-line me-2"></i>{{ $q->status === 'requested' ? 'Respond' : 'Edit' }}</a></li>
                                            @endcan
                                            @can('orders.create')
                                            @if($q->status !== 'converted')
                                            <li>
                                                <form action="{{ route('order.from.quotation', $q->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="button" class="dropdown-item text-success convert-btn"
                                                            data-quotation="{{ $q->quotation_no }}"
                                                            data-status="{{ $q->statusLabel() }}">
                                                        <i class="ri-arrow-right-line me-2"></i>Convert to Order
                                                    </button>
                                                </form>
                                            </li>
                                            @else
                                            <li>
                                                <a class="dropdown-item text-success" href="{{ route('orders.index') }}">
                                                    <i class="ri-check-line me-2"></i>Already converted
                                                </a>
                                            </li>
                                            @endif
                                            @endcan
                                            @if($q->status === 'negotiating')
                                            @can('quotations.deny')
                                            <li>
                                                <form action="{{ route('quotation.deny', $q->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="ri-close-line me-2"></i>Deny</button>
                                                </form>
                                            </li>
                                            @endcan
                                            @endif
                                            @can('quotations.delete')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('quotation.delete', $q->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn"><i class="ri-delete-bin-line me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $q->quotation_no }}</span></td>
                                <td>{{ $q->query_received_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $q->customer->name ?? '—' }}</td>
                                <td>{{ $q->items_count }}</td>
                                <td class="text-end">৳ {{ number_format($q->grand_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($q->total_profit, 2) }}</td>
                                <td class="text-end">{{ number_format($q->profit_margin, 2) }}%</td>
                                <td>{!! $q->submitted_to_customer ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                                <td><span class="badge bg-{{ $qc[$q->status] ?? 'secondary' }}">{{ $q->statusLabel() }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fixed Popper strategy so the responsive-table overflow doesn't clip the open menu.
    document.querySelectorAll('#quotationsTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this quotation?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    // Converting is a one-way step — confirm, and say so when the customer hasn't accepted yet.
    document.querySelectorAll('.convert-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        const accepted = btn.dataset.status === 'Accepted';
        Swal.fire({
            title: 'Convert ' + btn.dataset.quotation + ' to an order?',
            text: accepted
                ? 'Its items, rates and costs carry over to a new order.'
                : 'This quotation is ' + btn.dataset.status.toLowerCase() + ' — the customer has not accepted it yet.',
            icon: accepted ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, convert',
            confirmButtonColor: '#16a34a',
        }).then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
