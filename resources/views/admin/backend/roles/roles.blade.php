@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Roles &amp; Permissions</h4>
                <small class="text-muted">Control which role can do what</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Roles</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Roles</h5>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Role
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Users</th>
                                <th>Permissions</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $index => $role)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    {{ $role->name }}
                                    @if($role->is_system)<span class="badge bg-info ms-1">System</span>@endif
                                </td>
                                <td>{{ $role->description ?: '—' }}</td>
                                <td>{{ $role->users_count }}</td>
                                <td>{{ $role->isAdmin() ? 'All' : $role->permissions->count() }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-role-btn"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-role="{{ json_encode(['id' => $role->id, 'name' => $role->name, 'description' => $role->description, 'slug' => $role->slug, 'permissions' => $role->permissions->pluck('id')]) }}"
                                                title="Edit permissions">
                                            <i class="ri-shield-keyhole-line"></i> Permissions
                                        </button>
                                        @unless($role->is_system)
                                        <form action="{{ route('role.delete', $role->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-role-btn" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@foreach(['add' => 'addModal', 'edit' => 'editModal'] as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('role.store') : url('roles') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" data-field="name" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" data-field="description">
                        </div>
                    </div>

                    <label class="form-label fw-semibold d-block mb-2">Permissions</label>
                    <div class="ct-admin-note alert alert-info py-2 px-3 d-none" data-admin-note>
                        <i class="ri-information-line me-1"></i> The Admin role always has full access; its permissions can't be changed.
                    </div>
                    <div class="row g-3">
                        @foreach($permissions as $group => $perms)
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="fw-semibold small text-muted mb-1">{{ $group }}</div>
                                @foreach($perms as $perm)
                                <div class="form-check">
                                    <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="{{ $mode }}_perm_{{ $perm->id }}">
                                    <label class="form-check-label" for="{{ $mode }}_perm_{{ $perm->id }}">{{ $perm->name }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
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
        const r = JSON.parse(e.relatedTarget.dataset.role);
        const form = document.getElementById('editForm');
        form.action = '{{ url('roles') }}/' + r.id;
        form.querySelector('[data-field="name"]').value = r.name || '';
        form.querySelector('[data-field="description"]').value = r.description || '';

        const isAdmin = r.slug === 'admin';
        const ids = (r.permissions || []).map(Number);
        form.querySelectorAll('.perm-check').forEach(function (cb) {
            cb.checked = isAdmin || ids.includes(Number(cb.value));
            cb.disabled = isAdmin;
        });
        form.querySelector('[data-admin-note]').classList.toggle('d-none', !isAdmin);
    });

    document.querySelectorAll('.delete-role-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this role?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
