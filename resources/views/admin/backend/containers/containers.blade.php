@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'booked' => 'secondary', 'in_transit' => 'info', 'at_port' => 'warning',
        'released' => 'primary', 'delivered' => 'success',
    ];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Containers &amp; Shipments</h4>
                <small class="text-muted">Group orders into containers &amp; distribute shared costs</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Containers</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Container List</h5>
                @can('containers.create')
                <a href="{{ route('container.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Container
                </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <x-data-table id="containersTable" export-name="containers">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Code</th>
                                <th>Shipment No</th>
                                <th data-filter="Type">Type</th>
                                <th>Container No</th>
                                <th>Transport</th>
                                <th>Shipping Line</th>
                                <th class="text-end">Orders</th>
                                <th>ETD</th>
                                <th>ETA</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($containers as $c)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="{{ route('container.show', $c->id) }}"><i class="ri-eye-line me-2"></i>View</a></li>
                                            @can('containers.edit')
                                            <li><a class="dropdown-item" href="{{ route('container.edit', $c->id) }}"><i class="ri-edit-line me-2"></i>Edit</a></li>
                                            @endcan
                                            @can('containers.lists.print')
                                            <li><a class="dropdown-item" href="{{ route('container.packing.list', $c->id) }}" target="_blank"><i class="ri-file-list-3-line me-2"></i>Packing List</a></li>
                                            <li><a class="dropdown-item" href="{{ route('container.loading.list', $c->id) }}" target="_blank"><i class="ri-file-list-2-line me-2"></i>Loading List</a></li>
                                            @endcan
                                            @can('containers.delete')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('container.delete', $c->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn"><i class="ri-delete-bin-line me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $c->container_code }}</span></td>
                                <td>{{ $c->shipment_no ?: '—' }}</td>
                                <td><span class="badge bg-{{ $c->shipment_type === 'lcl' ? 'warning text-dark' : 'primary' }}">{{ strtoupper($c->shipment_type ?? 'fcl') }}</span></td>
                                <td>{{ $c->container_number ?: '—' }}</td>
                                <td>{{ $c->transport_mode ?: '—' }}</td>
                                <td>{{ $c->shipping_line ?: '—' }}</td>
                                <td class="text-end">{{ $c->orders_count }}</td>
                                <td>{{ $c->etd?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $c->eta?->format('d M Y') ?: '—' }}</td>
                                <td><span class="badge bg-{{ $statusColors[$c->status] ?? 'secondary' }}">{{ $c->statusLabel() }}</span></td>
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
    document.querySelectorAll('#containersTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this container?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
