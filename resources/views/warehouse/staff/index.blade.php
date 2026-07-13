@extends('warehouse.layouts.master')
@section('title', 'Staff')
@section('warehouse')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="fs-18 fw-semibold m-0">Staff &amp; Salary</h4>
                <small class="text-muted">{{ $warehouse->name }} — monthly payroll ৳ {{ number_format($monthlyPayroll, 2) }}.</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="ri-add-line me-1"></i> Add Staff</button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="staffTable" export-name="staff">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Phone</th>
                                <th class="text-end">Monthly Salary</th>
                                <th class="text-end">Docs</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staff as $s)
                            <tr>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('warehouse.staff.show', $s->id) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="ri-eye-line"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn" title="Edit" data-row="{{ json_encode($s->only(['id','name','designation','phone','email','join_date','monthly_salary','note','is_active'])) }}"><i class="ri-edit-line"></i></button>
                                        <form action="{{ route('warehouse.staff.delete', $s->id) }}" method="POST" class="m-0">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
                                    </div>
                                </td>
                                <td class="fw-semibold">{{ $s->name }}</td>
                                <td>{{ $s->designation ?: '—' }}</td>
                                <td>{{ $s->phone ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($s->monthly_salary, 2) }}</td>
                                <td class="text-end">{{ $s->documents_count }}</td>
                                <td>{!! $s->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
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
<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('warehouse.staff.store') : '' }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" data-field="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input type="text" class="form-control" name="designation" data-field="designation" placeholder="e.g. Loader, Supervisor">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" data-field="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" data-field="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Join Date</label>
                        <input type="date" class="form-control" name="join_date" data-field="join_date">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monthly Salary</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="monthly_salary" data-field="monthly_salary">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" data-field="note" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" data-field="is_active" id="{{ $mode }}Active" checked>
                            <label class="form-check-label fw-semibold" for="{{ $mode }}Active">Active</label>
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
@endsection

@section('warehouse_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', function () {
        const r = JSON.parse(btn.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('warehouse/staff') }}/' + r.id;
        form.querySelectorAll('[data-field]').forEach(function (f) {
            const key = f.dataset.field;
            if (f.type === 'checkbox') { f.checked = !!r[key]; }
            else { f.value = (r[key] ?? '') === null ? '' : (r[key] ?? ''); }
        });
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }));

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Remove this staff member?', text: 'Their documents and salary history will be removed too.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, remove', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
