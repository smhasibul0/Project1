@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Cost Categories</h4>
                <small class="text-muted">Cost types for order costs (freight, customs, LC, etc.)</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Cost Categories</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Cost Categories</h5>
                @can('cost-categories.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Category
                </button>
                @endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr><th>#</th><th>Name</th><th>Status</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $index => $category)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $category->name }}</td>
                                <td><span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @can('cost-categories.edit')
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-row="{{ json_encode($category) }}"><i class="ri-edit-line"></i></button>
                                                @endcan
                                        @can('cost-categories.delete')
                                        <form action="{{ route('cost.category.delete', $category->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No cost categories yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    // Only build the dialogs this role is allowed to submit.
    $pageModals = array_filter([
        'add' => auth()->user()->can('cost-categories.create') ? 'addModal' : null,
        'edit' => auth()->user()->can('cost-categories.edit') ? 'editModal' : null,
    ]);
@endphp

@foreach($pageModals as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('cost.category.store') : url('cost-categories') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Cost Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" data-field="name" required placeholder="e.g. Freight, Customs, LC Charge">
                    <div class="form-check mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" data-field="is_active" id="{{ $mode }}Active" checked>
                        <label class="form-check-label" for="{{ $mode }}Active">Active</label>
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
    document.getElementById('editModal')?.addEventListener('show.bs.modal', function (e) {
        const r = JSON.parse(e.relatedTarget.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('cost-categories') }}/' + r.id;
        form.querySelector('[data-field="name"]').value = r.name || '';
        form.querySelector('[data-field="is_active"]').checked = !!r.is_active;
    });
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this category?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
