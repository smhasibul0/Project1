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
                @can('orders.create')
                <a href="{{ route('orders.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Order
                </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <x-data-table id="ordersTable" export-name="orders">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Order No</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Container No</th>
                                <th>Items</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Due</th>
                                <th data-filter="Payment">Payment</th>
                                <th data-filter="Goods Status">Goods Status</th>
                                <th data-filter="Delivery">Delivery</th>
                                <th class="text-end">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $o)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            @can('orders.payments.create')
                                            <li>
                                                <button type="button" class="dropdown-item pay-btn"
                                                    data-id="{{ $o->id }}"
                                                    data-order="{{ $o->order_no }}"
                                                    data-business="{{ $o->customer->business_name ?? ($o->customer->name ?? '—') }}"
                                                    data-name="{{ $o->customer->name ?? '—' }}"
                                                    data-total="{{ number_format($o->total_amount, 2) }}"
                                                    data-due="{{ number_format($o->due_amount, 2, '.', '') }}">
                                                    <i class="ri-money-dollar-circle-line me-2"></i>Pay
                                                </button>
                                            </li>
                                            @endcan
                                            <li><a class="dropdown-item" href="{{ route('order.show', $o->id) }}"><i class="ri-eye-line me-2"></i>View</a></li>
                                            @can('orders.invoice')
                                            <li><a class="dropdown-item" href="{{ route('order.invoice', $o->id) }}" target="_blank"><i class="ri-file-text-line me-2"></i>Invoice</a></li>
                                            @endcan
                                            @can('orders.edit')
                                            <li><a class="dropdown-item" href="{{ route('order.edit', $o->id) }}"><i class="ri-edit-line me-2"></i>Edit</a></li>
                                            @endcan
                                            @can('orders.delete')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('order.delete', $o->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn"><i class="ri-delete-bin-line me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                            @endcan
                                            <x-history-link :record="$o" />
                                        </ul>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $o->order_no }}</span></td>
                                <td>{{ $o->order_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $o->customer->name ?? '—' }}</td>
                                <td>{{ $o->containerNumbers() ?: '—' }}</td>
                                <td>{{ $o->items_count }}</td>
                                <td class="text-end">৳ {{ number_format($o->total_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($o->received_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($o->due_amount, 2) }}</td>
                                <td><span class="badge bg-{{ $o->payment_status === 'paid' ? 'success' : ($o->payment_status === 'partial' ? 'warning' : 'secondary') }} text-capitalize">{{ $o->payment_status }}</span></td>
                                <td><span class="badge bg-info text-capitalize">{{ str_replace('_', ' ', $o->goods_status) }}</span></td>
                                <td><span class="badge bg-{{ $o->delivery_status === 'delivered' ? 'success' : 'secondary' }} text-capitalize">{{ $o->delivery_status }}</span></td>
                                <td class="text-end">৳ {{ number_format($o->profit, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Add Payment modal ===================== --}}
@can('orders.payments.create')
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="payForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="bg-light rounded p-2 h-100 small">
                                <div><strong>Customer:</strong> <span id="payName">—</span></div>
                                <div><strong>Business:</strong> <span id="payBusiness">—</span></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-2 h-100 small">
                                <div><strong>Reference No:</strong> <span id="payRef">—</span></div>
                                <div><strong>Total Amount:</strong> ৳ <span id="payTotal">0.00</span></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light rounded p-2 h-100 small">
                                <div><strong>Due Amount:</strong> ৳ <span id="payDueLabel">0.00</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-control" name="method">
                                @foreach(['Cash', 'Bank Transfer', 'Cheque', 'Mobile Banking', 'Other'] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Paid on <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="payDate" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="payAmount" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Account</label>
                            <select class="form-control" name="payment_account_id">
                                <option value="">-- None (no ledger entry) --</option>
                                <x-account-options :accounts="$accounts" />
                            </select>
                        </div>
                        <x-currency-choice direction="in" />
                        <div class="col-md-6">
                            <label class="form-label">Attach Document</label>
                            <input type="file" class="form-control" name="attachment" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                            <small class="text-muted">.pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Payment Note</label>
                            <textarea class="form-control" name="note" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary px-4">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fixed Popper strategy so the responsive-table overflow doesn't clip the open menu.
    document.querySelectorAll('#ordersTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this order?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    // ----- Pay modal: fill from the clicked row -----
    const payModalEl = document.getElementById('payModal');
    if (payModalEl) {
        const payModal = new bootstrap.Modal(payModalEl);
        const payForm = document.getElementById('payForm');
        const today = new Date().toISOString().slice(0, 10);

        document.querySelectorAll('.pay-btn').forEach(btn => btn.addEventListener('click', function () {
            const d = btn.dataset;
            payForm.action = '{{ url('orders') }}/' + d.id + '/payment';
            payForm.reset();
            document.getElementById('payName').textContent = d.name;
            document.getElementById('payBusiness').textContent = d.business;
            document.getElementById('payRef').textContent = d.order;
            document.getElementById('payTotal').textContent = d.total;
            document.getElementById('payDueLabel').textContent = Number(d.due).toFixed(2);
            document.getElementById('payDate').value = today;
            document.getElementById('payAmount').value = Number(d.due) > 0 ? Number(d.due).toFixed(2) : '';
            payModal.show();
        }));
    }
});
</script>
@endsection
