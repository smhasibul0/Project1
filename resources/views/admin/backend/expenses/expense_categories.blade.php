@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Expense Categories</h4>
                <small class="text-muted">Warehouse operating-expense types (parent → sub-category), e.g. Utilities → Electricity.</small>
            </div>
            <div class="text-end">
                @can('expense-categories.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal" id="addParentBtn">
                    <i class="ri-add-line me-1"></i> Add Category
                </button>
                @endcan
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Category</th><th>Status</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($parents as $parent)
                            <tr class="table-light">
                                <td class="fw-semibold"><i class="ri-folder-2-line me-1 text-muted"></i>{{ $parent->name }}</td>
                                <td><span class="badge bg-{{ $parent->is_active ? 'success' : 'secondary' }}">{{ $parent->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @can('expense-categories.create')<button type="button" class="btn btn-sm btn-outline-secondary add-sub-btn" data-parent="{{ $parent->id }}" data-parent-name="{{ $parent->name }}" title="Add sub-category"><i class="ri-add-line"></i></button>@endcan
                                        @can('expense-categories.edit')<button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-row="{{ json_encode($parent->only(['id','name','is_active'])) }}"><i class="ri-edit-line"></i></button>@endcan
                                        @can('expense-categories.delete')<form action="{{ route('expense.category.delete', $parent->id) }}" method="POST" class="m-0">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="ri-delete-bin-line"></i></button></form>@endcan
                                    </div>
                                </td>
                            </tr>
                            @foreach($parent->children as $child)
                            <tr>
                                <td class="ps-4"><i class="ri-corner-down-right-line me-1 text-muted"></i>{{ $child->name }}</td>
                                <td><span class="badge bg-{{ $child->is_active ? 'success' : 'secondary' }}-subtle text-{{ $child->is_active ? 'success' : 'secondary' }}">{{ $child->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @can('expense-categories.edit')<button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-row="{{ json_encode($child->only(['id','name','is_active'])) }}"><i class="ri-edit-line"></i></button>@endcan
                                        @can('expense-categories.delete')<form action="{{ route('expense.category.delete', $child->id) }}" method="POST" class="m-0">@csrf @method('DELETE')<button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="ri-delete-bin-line"></i></button></form>@endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No expense categories yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add modal (category or sub-category) --}}
@can('expense-categories.create')
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('expense.category.store') }}" method="POST">
                @csrf
                <input type="hidden" name="parent_id" id="addParentId">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required placeholder="e.g. Utilities or Electricity">
                    <div class="form-check mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="addActive" checked>
                        <label class="form-check-label" for="addActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- Edit modal --}}
@can('expense-categories.edit')
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" data-field="name" required>
                    <div class="form-check mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" data-field="is_active" id="editActive">
                        <label class="form-check-label" for="editActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    // The add dialog and its buttons only exist for roles that may create a category.
    const addModalEl = document.getElementById('addModal');
    const addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;

    // "Add Category" (top button) → parent; per-row "+" → sub-category of that parent.
    document.getElementById('addParentBtn')?.addEventListener('click', function () {
        document.getElementById('addParentId').value = '';
        document.getElementById('addModalTitle').textContent = 'Add Category';
    });
    document.querySelectorAll('.add-sub-btn').forEach(btn => btn.addEventListener('click', function () {
        document.getElementById('addParentId').value = btn.dataset.parent;
        document.getElementById('addModalTitle').textContent = 'Add sub-category to ' + btn.dataset.parentName;
        addModal?.show();
    }));

    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', function () {
        const r = JSON.parse(btn.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('expense-categories') }}/' + r.id;
        form.querySelector('[data-field="name"]').value = r.name || '';
        form.querySelector('[data-field="is_active"]').checked = !!r.is_active;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }));

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this category?', text: 'Sub-categories will be removed too.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
