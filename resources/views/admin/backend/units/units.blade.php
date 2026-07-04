@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Units</h4>
                <small class="text-muted">Units of measure &amp; conversions</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Units</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Units</h5>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Unit
                </button>
            </div>
            <div class="card-body p-0">
                <x-data-table id="unitsTable" export-name="units">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">#</th>
                                <th>Name</th>
                                <th>Short Name</th>
                                <th data-filter="Base Unit">Base Unit</th>
                                <th>Conversion</th>
                                <th class="text-end dt-noexport">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($units as $index => $unit)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $unit->name }}</td>
                                <td>{{ $unit->short_name ?: '—' }}</td>
                                <td>{{ $unit->baseUnit->name ?? '—' }}</td>
                                <td>{{ $unit->base_unit_id ? '1 '.$unit->short_name.' = '.rtrim(rtrim(number_format($unit->conversion_factor, 4), '0'), '.').' '.($unit->baseUnit->short_name ?? '') : '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-row="{{ json_encode($unit) }}" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <form action="{{ route('unit.delete', $unit->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
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

@foreach(['add' => 'addModal', 'edit' => 'editModal'] as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('unit.store') : url('units') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" data-field="name" required placeholder="e.g. Piece, Kilogram, Dozen">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Short Name</label>
                        <input type="text" class="form-control" name="short_name" data-field="short_name" placeholder="pc, kg">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Base Unit</label>
                        <select class="form-control" name="base_unit_id" data-field="base_unit_id">
                            <option value="">— None (this is a base unit) —</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Conversion Factor</label>
                        <input type="number" step="0.0001" min="0" class="form-control" name="conversion_factor" data-field="conversion_factor" value="1">
                        <small class="text-muted">e.g. Dozen → base Piece = 12</small>
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
    document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
        const r = JSON.parse(e.relatedTarget.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('units') }}/' + r.id;
        form.querySelectorAll('[data-field]').forEach(f => f.value = (r[f.dataset.field] ?? '') === null ? '' : (r[f.dataset.field] ?? ''));
    });
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this unit?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
