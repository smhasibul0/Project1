@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Warehouses</h4>
                <small class="text-muted">Storage locations</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Warehouses</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Warehouses</h5>
                @can('warehouses.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Warehouse
                </button>
                @endcan
            </div>
            <div class="card-body p-0">
                <x-data-table id="warehousesTable" export-name="warehouses">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th class="dt-noexport">#</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($warehouses as $index => $warehouse)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            {{-- Stepping into a warehouse's own screens stays admin-only:
                                                 EnsureWarehouseAccess turns anyone else away. --}}
                                            @if(auth()->user()->isAdmin() && auth()->user()->can('warehouses.enter'))
                                            <li>
                                                <a href="{{ route('warehouse.manage', $warehouse->id) }}" class="dropdown-item">
                                                    <i class="ri-settings-3-line me-1"></i> Manage
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            @endif
                                            @can('warehouses.edit')
                                            <li>
                                                <button type="button" class="dropdown-item edit-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-row="{{ json_encode($warehouse) }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            @endcan
                                            @can('warehouses.delete')
                                            <li>
                                                <form action="{{ route('warehouse.delete', $warehouse->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn">
                                                        <i class="ri-delete-bin-line me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $warehouse->name }}</td>
                                <td>{{ $warehouse->code ?: '—' }}</td>
                                <td>{{ $warehouse->phone ?: '—' }}</td>
                                <td>{{ $warehouse->address ?: '—' }}</td>
                                <td>
                                    @if($warehouse->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
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

@php
    // Only build the dialogs this role is allowed to submit.
    $pageModals = array_filter([
        'add' => auth()->user()->can('warehouses.create') ? 'addModal' : null,
        'edit' => auth()->user()->can('warehouses.edit') ? 'editModal' : null,
    ]);
@endphp

@foreach($pageModals as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('warehouse.store') : url('warehouses') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Warehouse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" data-field="name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code</label>
                        <input type="text" class="form-control" name="code" data-field="code">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" data-field="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" data-field="email">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" data-field="address" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" data-field="is_active" value="1" {{ $mode === 'add' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $mode === 'add' ? 'Save' : 'Update' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fixed Popper strategy so the responsive-table overflow doesn't clip the open menu.
    document.querySelectorAll('#warehousesTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    document.getElementById('editModal')?.addEventListener('show.bs.modal', function (e) {
        const r = JSON.parse(e.relatedTarget.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('warehouses') }}/' + r.id;
        form.querySelectorAll('[data-field]').forEach(function (f) {
            if (f.type === 'checkbox') { f.checked = !!Number(r[f.dataset.field]); }
            else { f.value = (r[f.dataset.field] ?? '') === null ? '' : (r[f.dataset.field] ?? ''); }
        });
    });
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this warehouse?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
