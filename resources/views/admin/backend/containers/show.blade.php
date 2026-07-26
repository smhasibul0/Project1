@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'booked' => 'secondary', 'in_transit' => 'info', 'at_port' => 'warning',
        'released' => 'primary', 'delivered' => 'success',
    ];
    $basis = $container->allocation_basis;
    $costsTotal = (float) $container->costs->sum('amount');
    $basisValue = fn ($o) => match ($basis) {
        'weight' => (float) $o->pivot->weight,
        'ctn' => (float) $o->pivot->ctn,
        'equal' => 1.0,
        default => (float) $o->pivot->cbm,
    };
    $basisTotal = (float) $container->orders->sum($basisValue);
    $share = fn ($o) => ($costsTotal > 0 && $basisTotal > 0) ? round($costsTotal * $basisValue($o) / $basisTotal, 2) : 0.0;
    $totalCtn = (float) $container->orders->sum(fn ($o) => (float) $o->pivot->ctn);
    $totalWeight = (float) $container->orders->sum(fn ($o) => (float) $o->pivot->weight);
    $totalCbm = (float) $container->orders->sum(fn ($o) => (float) $o->pivot->cbm);

    // Derived from the assigned orders (not entered on the container).
    $clientNames = $container->orders->map(fn ($o) => $o->customer->name ?? null)->filter()->unique()->implode(', ');
    $goodsDesc = $container->orders->flatMap->items->map(fn ($it) => $it->item_description)->filter()->unique()->implode(', ');
    $shippingMarks = $container->orders->map(fn ($o) => $o->shipping_mark)->filter()->unique()->implode(', ');
    $lcNumbers = $container->orders->flatMap->lcs->map(fn ($l) => $l->lc_number)->filter()->unique()->implode(', ');
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">
                    Container {{ $container->container_code }}
                    @if($container->shipment_no)<small class="text-muted">/ {{ $container->shipment_no }}</small>@endif
                    <span class="badge bg-{{ $container->shipment_type === 'lcl' ? 'warning text-dark' : 'primary' }} align-middle ms-1">{{ strtoupper($container->shipment_type ?? 'fcl') }}</span>
                </h4>
                <small class="text-muted">{{ $container->container_number ?: ($container->shipment_type === 'lcl' ? 'Consolidator container (LCL)' : '—') }}{{ $container->container_size ? ' · '.$container->container_size : '' }}</small>
            </div>
            <div class="text-end">
                <a href="{{ route('container.packing.list', $container->id) }}" target="_blank" class="btn btn-success btn-sm"><i class="ri-file-list-3-line me-1"></i> Packing List</a>
                <a href="{{ route('container.loading.list', $container->id) }}" target="_blank" class="btn btn-success btn-sm"><i class="ri-file-list-2-line me-1"></i> Loading List</a>
                <a href="{{ route('container.edit', $container->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>
                <a href="{{ route('container.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Shipment Details</h6>
                <span class="badge bg-{{ $statusColors[$container->status] ?? 'secondary' }}">{{ $container->statusLabel() }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">LC Number</small><strong>{{ $lcNumbers ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Transport Mode</small>{{ $container->transport_mode ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Shipping / Air Line</small>{{ $container->shipping_line ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Booking Ref (HBL/MBL)</small>{{ $container->booking_ref ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">Client Name</small>{{ $clientNames ?: '—' }}</div>
                <div class="col-md-6"><small class="text-muted d-block">Goods Description</small>{{ $goodsDesc ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Shipping Marks</small>{{ $shippingMarks ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">Quantity (CTN)</small><strong>{{ rtrim(rtrim(number_format($totalCtn, 2), '0'), '.') }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Total Weight (KG)</small><strong>{{ rtrim(rtrim(number_format($totalWeight, 2), '0'), '.') }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Volume (CBM)</small><strong>{{ rtrim(rtrim(number_format($totalCbm, 4), '0'), '.') }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Allocation Basis</small>{{ $allocationBases[$container->allocation_basis] ?? $container->allocation_basis }}</div>

                @if($container->shipment_type === 'lcl')
                <div class="col-md-3"><small class="text-muted d-block">LCL Rate (per CBM)</small><strong>{{ $container->lcl_rate !== null ? number_format($container->lcl_rate, 2) : '—' }}</strong></div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Estimated LCL Freight</small>
                    <strong>{{ number_format($container->lcl_rate * $totalCbm, 2) }}</strong>
                    <small class="text-muted">({{ number_format((float) $container->lcl_rate, 2) }} &times; {{ rtrim(rtrim(number_format($totalCbm, 4), '0'), '.') }} CBM)</small>
                </div>
                @endif

                <div class="col-md-3"><small class="text-muted d-block">Port of Loading</small>{{ $container->port_of_loading ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Port of Discharge</small>{{ $container->port_of_discharge ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">ETD</small>{{ $container->etd?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">ETA</small>{{ $container->eta?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-6"><small class="text-muted d-block">Staffing Notes</small>{{ $container->staffing_notes ?: '—' }}</div>
            </div>
        </div>

        {{-- Status update --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Update Shipment Status</h6></div>
            <div class="card-body">
                <form action="{{ route('container.status', $container->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <select class="form-control" name="status" required>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($container->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="ri-refresh-line me-1"></i> Update Status</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Assigned orders + allocation --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Assigned Orders ({{ $container->orders->count() }})</h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal"><i class="ri-add-line me-1"></i> Assign Order</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Order</th><th>Client</th><th>Shipping Mark</th>
                                <th class="text-end">CTN</th><th class="text-end">Weight (KG)</th><th class="text-end">CBM</th>
                                <th class="text-end">Allocated Cost</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($container->orders as $o)
                            <tr>
                                <td><a href="{{ route('order.show', $o->id) }}">{{ $o->order_no }}</a></td>
                                <td>{{ $o->customer->name ?? '—' }}</td>
                                <td>{{ $o->shipping_mark ?: '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($o->pivot->ctn, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($o->pivot->weight, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($o->pivot->cbm, 4), '0'), '.') }}</td>
                                <td class="text-end">৳ {{ number_format($share($o), 2) }}</td>
                                <td class="text-end">
                                    <form action="{{ route('container.order.delete', [$container->id, $o->id]) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-remove"><i class="ri-close-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-muted text-center py-3">No orders assigned yet</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="3">Totals</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($totalCtn, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($totalWeight, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($totalCbm, 4), '0'), '.') }}</td>
                                <td class="text-end">৳ {{ number_format($costsTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Container costs --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Container Costs ({{ $container->costs->count() }}) <small class="text-muted">— distributed to orders by {{ $allocationBases[$container->allocation_basis] ?? $container->allocation_basis }}</small></h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal"><i class="ri-add-line me-1"></i> Add Cost</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr><th>Category</th><th>Title</th><th>Date</th><th>Account</th><th>Doc</th><th class="text-end">Amount</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($container->costs as $c)
                            <tr>
                                <td>{{ $c->category->name ?? '—' }}</td>
                                <td>{{ $c->title }}</td>
                                <td>{{ $c->cost_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $c->paymentAccount->name ?? '—' }}</td>
                                <td>@if($c->attachment)<a href="{{ asset('upload/containers/'.$c->attachment) }}" target="_blank"><i class="ri-attachment-line"></i></a>@else—@endif</td>
                                <td class="text-end">৳ {{ number_format($c->amount, 2) }}</td>
                                <td class="text-end">
                                    <form action="{{ route('container.cost.delete', [$container->id, $c->id]) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-remove"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-muted text-center py-3">No container costs recorded</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold"><td colspan="5">Total</td><td class="text-end">৳ {{ number_format($costsTotal, 2) }}</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Documents --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Documents ({{ $container->documents->count() }})</h6></div>
            <div class="card-body">
                <form action="{{ route('container.document.store', $container->id) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Document Type</label>
                        <select class="form-control" name="type" required>
                            @foreach($documentTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Provided By</label>
                        <input type="text" class="form-control" name="provided_by" placeholder="e.g. RTC, Bank">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="file" required accept=".pdf,.csv,.zip,.doc,.docx,.xls,.xlsx,.jpeg,.jpg,.png">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="ri-upload-2-line me-1"></i> Upload</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Type</th><th>Provided By</th><th>File</th><th></th></tr></thead>
                        <tbody>
                            @forelse($container->documents as $d)
                            <tr>
                                <td>{{ $d->type }}</td>
                                <td>{{ $d->provided_by ?: '—' }}</td>
                                <td><a href="{{ asset('upload/containers/'.$d->file) }}" target="_blank"><i class="ri-attachment-line me-1"></i>View</a></td>
                                <td class="text-end">
                                    <form action="{{ route('container.document.delete', [$container->id, $d->id]) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-remove"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No documents uploaded</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Assign Order modal ===================== --}}
<div class="modal fade" id="addOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('container.order.store', $container->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Order to {{ $container->container_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Order <span class="text-danger">*</span></label>
                        <select class="form-control" name="order_id" id="assignOrderSelect" required>
                            <option value="">-- Select Order --</option>
                            @foreach($assignableOrders as $o)
                                <option value="{{ $o->id }}">{{ $o->order_no }} — {{ $o->customer->name ?? '—' }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Re-assigning an order already in this container updates its loaded quantity.</small>
                    </div>

                    {{-- Order items: tick which ride in this container; totals auto-sum. --}}
                    <div class="col-12">
                        <label class="form-label mb-1">Items in this container</label>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0 align-middle" id="assignItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width:34px;"></th><th>Item</th>
                                        <th class="text-end" style="width:110px;">CTN</th>
                                        <th class="text-end" style="width:120px;">Weight (KG)</th>
                                        <th class="text-end" style="width:120px;">CBM</th>
                                    </tr>
                                </thead>
                                <tbody id="assignItemsBody">
                                    <tr><td colspan="5" class="text-muted text-center py-3">Select an order to load its items.</td></tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-semibold">
                                        <td colspan="2">Total</td>
                                        <td class="text-end"><span id="assignTotalCtn">0</span></td>
                                        <td class="text-end"><span id="assignTotalWeight">0</span></td>
                                        <td class="text-end"><span id="assignTotalCbm">0</span> CBM</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <small class="text-muted">Untick an item or edit its cartons/CBM for a partial (split) shipment. Totals are stored for this container.</small>
                    </div>

                    {{-- Submitted totals (kept in sync by the items above; editable for orders without items). --}}
                    <div class="col-md-4">
                        <label class="form-label">Total CTN</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="ctn" id="assignCtn" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Weight (KG)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="weight" id="assignWeight" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total CBM</label>
                        <input type="number" step="0.0001" min="0" class="form-control" name="cbm" id="assignCbm" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" class="form-control" name="remarks">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary px-4">Assign</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== Add Cost modal ===================== --}}
<div class="modal fade" id="addCostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('container.cost.store', $container->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Container Cost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="cost_category_id">
                            <option value="">-- Uncategorized --</option>
                            @foreach($costCategories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Ocean freight, Container charge">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cost Date</label>
                        <input type="date" class="form-control" name="cost_date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Pay from Account <small class="text-muted">(debits the account ledger)</small></label>
                        <select class="form-control" name="payment_account_id">
                            <option value="">-- None (no ledger entry) --</option>
                            <x-account-options :accounts="$accounts" />
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attach Document</label>
                        <input type="file" class="form-control" name="attachment" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Note</label>
                        <input type="text" class="form-control" name="note">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary px-4">Save Cost</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@php
    $assignOrderItems = $assignableOrders->mapWithKeys(fn ($o) => [$o->id => $o->items->map(fn ($it) => [
        'description' => $it->item_description ?: 'Item',
        'ctn' => (float) $it->package_quantity,
        'weight' => (float) ($it->actual_weight ?: $it->net_weight),
        'cbm' => (float) $it->cbm,
    ])->values()]);
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.confirm-remove').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Are you sure?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    // ----- Assign modal: item-level entry with an auto-summed total CBM -----
    const orderItems = @json($assignOrderItems);
    const body = document.getElementById('assignItemsBody');
    const trim = (n, d) => { const v = (Number(n) || 0).toFixed(d); return v.replace(/\.?0+$/, '') || '0'; };

    function recalcAssign() {
        let ctn = 0, weight = 0, cbm = 0;
        body.querySelectorAll('.assign-row').forEach(function (row) {
            if (!row.querySelector('.assign-check').checked) { return; }
            ctn += Number(row.querySelector('.assign-ctn').value) || 0;
            weight += Number(row.querySelector('.assign-weight').value) || 0;
            cbm += Number(row.querySelector('.assign-cbm').value) || 0;
        });
        document.getElementById('assignCtn').value = ctn;
        document.getElementById('assignWeight').value = weight;
        document.getElementById('assignCbm').value = cbm;
        document.getElementById('assignTotalCtn').textContent = trim(ctn, 2);
        document.getElementById('assignTotalWeight').textContent = trim(weight, 2);
        document.getElementById('assignTotalCbm').textContent = trim(cbm, 4);
    }

    function renderItems(orderId) {
        const items = orderItems[orderId] || [];
        if (!items.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">This order has no items — enter the totals below manually.</td></tr>';
            ['assignCtn', 'assignWeight', 'assignCbm'].forEach(id => document.getElementById(id).value = 0);
            ['assignTotalCtn', 'assignTotalWeight', 'assignTotalCbm'].forEach(id => document.getElementById(id).textContent = '0');
            return;
        }
        body.innerHTML = '';
        items.forEach(function (it) {
            const tr = document.createElement('tr');
            tr.className = 'assign-row';
            tr.innerHTML =
                '<td class="text-center"><input type="checkbox" class="form-check-input assign-check" checked></td>' +
                '<td>' + (it.description || 'Item') + '</td>' +
                '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end assign-ctn" value="' + it.ctn + '"></td>' +
                '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end assign-weight" value="' + it.weight + '"></td>' +
                '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end assign-cbm" value="' + it.cbm + '"></td>';
            body.appendChild(tr);
        });
        body.querySelectorAll('input').forEach(el => el.addEventListener('input', recalcAssign));
        body.querySelectorAll('.assign-check').forEach(el => el.addEventListener('change', recalcAssign));
        recalcAssign();
    }

    const orderSelect = document.getElementById('assignOrderSelect');
    if (orderSelect) {
        orderSelect.addEventListener('change', function () { renderItems(this.value); });
    }
});
</script>
@endsection
