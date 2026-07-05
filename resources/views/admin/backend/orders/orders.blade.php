@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Orders</h4>
                <small class="text-muted">Order management &amp; tracking</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Orders</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Order List</h5>
                <a href="{{ route('orders.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Order
                </a>
            </div>
            <div class="card-body p-0">
                <x-data-table id="ordersTable" export-name="orders">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">#</th>
                                <th>Order No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Due</th>
                                <th data-filter="Payment">Payment</th>
                                <th data-filter="Goods Status">Goods Status</th>
                                <th data-filter="Delivery">Delivery</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end dt-noexport">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $index => $o)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge bg-light text-dark">{{ $o->order_no }}</span></td>
                                <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $o->customer->name ?? '—' }}</td>
                                <td>{{ $o->items_count }}</td>
                                <td class="text-end">৳ {{ number_format($o->total_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($o->received_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($o->due_amount, 2) }}</td>
                                <td><span class="badge bg-{{ $o->payment_status === 'paid' ? 'success' : ($o->payment_status === 'partial' ? 'warning' : 'secondary') }} text-capitalize">{{ $o->payment_status }}</span></td>
                                <td><span class="badge bg-info text-capitalize">{{ str_replace('_', ' ', $o->goods_status) }}</span></td>
                                <td><span class="badge bg-{{ $o->delivery_status === 'delivered' ? 'success' : 'secondary' }} text-capitalize">{{ $o->delivery_status }}</span></td>
                                <td class="text-end">৳ {{ number_format($o->profit, 2) }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('order.show', $o->id) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="ri-eye-line"></i></a>
                                        <a href="{{ route('order.edit', $o->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="ri-edit-line"></i></a>
                                        <form action="{{ route('order.delete', $o->id) }}" method="POST" class="m-0">
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
        Swal.fire({ title: 'Delete this order?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
