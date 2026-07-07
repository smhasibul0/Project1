@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Users</h4>
                <small class="text-muted">Manage system users &amp; their roles</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Users</h5>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add User
                </button>
            </div>
            <div class="card-body p-0">
                <x-data-table id="usersTable" export-name="users">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">#</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th data-filter="Role">Role</th>
                                <th data-filter="Status">Status</th>
                                <th class="text-end dt-noexport">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $index => $user)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ trim($user->first_name.' '.$user->last_name) }}</td>
                                <td>{{ $user->username }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?: '—' }}</td>
                                <td>{{ $user->role->name ?? '—' }}</td>
                                <td>
                                    @if($user->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-user-btn"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-user="{{ json_encode($user->only(['id','first_name','last_name','username','email','phone','role_id','contact_id','status'])) }}" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <form action="{{ route('user.toggle', $user->id) }}" method="POST" class="m-0">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $user->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                                <i class="ri-shut-down-line"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('user.delete', $user->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn" title="Delete">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('user.store') : url('users') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" data-field="first_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" data-field="last_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" data-field="username" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" data-field="email" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" data-field="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-control role-select" name="role_id" data-field="role_id" required>
                            <option value="">-- Select Role --</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" data-slug="{{ $role->slug }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 customer-contact-field" style="display:none;">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select class="form-control" name="contact_id" data-field="contact_id">
                            <option value="">-- Select Customer --</option>
                            @foreach($customerContacts as $c)
                                <option value="{{ $c->id }}">{{ $c->name ?: $c->business_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">The customer this login belongs to (portal access).</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password @if($mode === 'add')<span class="text-danger">*</span>@endif</label>
                        <input type="password" class="form-control" name="password" placeholder="{{ $mode === 'edit' ? 'Leave blank to keep current' : '' }}" {{ $mode === 'add' ? 'required' : '' }}>
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch mt-4">
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
    // Show the Customer selector only when the Customer role is chosen.
    function toggleContactField(select) {
        const form = select.closest('form');
        const field = form.querySelector('.customer-contact-field');
        const opt = select.options[select.selectedIndex];
        const isCustomer = opt && opt.dataset.slug === 'customer';
        field.style.display = isCustomer ? '' : 'none';
        field.querySelector('[name="contact_id"]').required = isCustomer;
    }
    document.querySelectorAll('.role-select').forEach(function (sel) {
        sel.addEventListener('change', () => toggleContactField(sel));
    });

    document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
        const u = JSON.parse(e.relatedTarget.dataset.user);
        const form = document.getElementById('editForm');
        form.action = '{{ url('users') }}/' + u.id;
        form.querySelectorAll('[data-field]').forEach(function (f) {
            if (f.type === 'checkbox') { f.checked = u.status === 'active'; }
            else { f.value = (u[f.dataset.field] ?? '') === null ? '' : (u[f.dataset.field] ?? ''); }
        });
        form.querySelector('[name="password"]').value = '';
        toggleContactField(form.querySelector('.role-select'));
    });

    document.querySelectorAll('.delete-user-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this user?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
