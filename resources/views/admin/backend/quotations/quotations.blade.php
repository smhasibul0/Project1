@extends('admin.admin_master')
@section('admin')

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
                <a href="{{ route('quotations.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Quotation
                </a>
            </div>
            <div class="card-body p-0">
                <x-data-table id="quotationsTable" export-name="quotations">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">#</th>
                                <th>Quotation No</th>
                                <th>Query Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th class="text-end">Grand Total</th>
                                <th class="text-end">Total Profit</th>
                                <th class="text-end">Margin</th>
                                <th data-filter="Submitted">Submitted</th>
                                <th data-filter="Status">Status</th>
                                <th class="text-end dt-noexport">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotations as $index => $q)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge bg-light text-dark">{{ $q->quotation_no }}</span></td>
                                <td>{{ $q->query_received_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $q->customer->name ?? '—' }}</td>
                                <td>{{ $q->items_count }}</td>
                                <td class="text-end">৳ {{ number_format($q->grand_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($q->total_profit, 2) }}</td>
                                <td class="text-end">{{ number_format($q->profit_margin, 2) }}%</td>
                                <td>{!! $q->submitted_to_customer ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                                <td><span class="badge bg-info text-capitalize">{{ $q->status }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('quotation.show', $q->id) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="ri-eye-line"></i></a>
                                        <a href="{{ route('quotation.edit', $q->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('quotation.delete', $q->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </div>
                                </td>
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
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this quotation?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
