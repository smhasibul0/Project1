@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Roles &amp; Permissions</h4>
                <small class="text-muted">Every module in the system, one permission per action</small>
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
                @can('roles.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Role
                </button>
                @endcan
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
                                <td>
                                    @if($role->isAdmin())
                                        <span class="badge bg-success">All {{ $permissionCount }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $role->permissions->count() }} of {{ $permissionCount }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <x-history-link :record="$role" as="button" />
                                        @can('roles.edit')
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-role-btn"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-role="{{ json_encode(['id' => $role->id, 'name' => $role->name, 'description' => $role->description, 'slug' => $role->slug, 'permissions' => $role->permissions->pluck('id')]) }}"
                                                title="Edit permissions">
                                            <i class="ri-shield-keyhole-line"></i> Permissions
                                        </button>
                                        @endcan
                                        @can('roles.delete')
                                        @unless($role->is_system)
                                        <form action="{{ route('role.delete', $role->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-role-btn" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endunless
                                        @endcan
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

<style>
    .perm-toolbar { position:sticky; top:0; z-index:3; background:#fff; border-bottom:1px solid #e2e8f0; padding:.7rem 0 .8rem; margin-bottom:.9rem; }
    .perm-module { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; height:100%; }
    .perm-module-head { display:flex; align-items:center; gap:.5rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:.5rem .7rem; }
    .perm-module-head label { font-weight:600; font-size:.82rem; color:#1e293b; margin:0; cursor:pointer; flex:1 1 auto; }
    .perm-module-count { font-size:.7rem; color:#64748b; white-space:nowrap; }
    .perm-row { display:flex; gap:.5rem; padding:.4rem .7rem; border-top:1px solid #f1f5f9; }
    .perm-row:first-of-type { border-top:none; }
    .perm-row:hover { background:#f8fafc; }
    .perm-row label { margin:0; cursor:pointer; }
    .perm-row .perm-name { font-size:.82rem; color:#334155; font-weight:500; display:block; }
    .perm-row .perm-desc { font-size:.74rem; color:#64748b; display:block; line-height:1.35; }
    .perm-row code { font-size:.68rem; color:#94a3b8; background:none; padding:0; }
    .perm-empty { display:none; text-align:center; color:#94a3b8; font-size:.85rem; padding:2rem 1rem; }
</style>

@foreach(['add' => 'addModal', 'edit' => 'editModal'] as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="{{ $mode }}Form" action="{{ $mode === 'add' ? route('role.store') : url('roles') }}" method="POST" data-perm-form>
                @csrf
                @if($mode === 'edit') @method('PUT') @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-2">
                        <div class="col-md-5">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" data-field="name" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" data-field="description">
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 d-none" data-admin-note>
                        <i class="ri-information-line me-1"></i> The Admin role always has full access; its permissions can't be changed.
                    </div>

                    {{-- Toolbar: search, bulk toggles, live count --}}
                    <div class="perm-toolbar d-flex align-items-center flex-wrap gap-2">
                        <div class="flex-grow-1" style="min-width:220px;">
                            <input type="search" class="form-control form-control-sm" data-perm-search
                                   placeholder="Search a module, action or permission key…">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-perm-all>Select all</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-perm-none>Clear all</button>
                        <span class="badge bg-light text-dark" data-perm-count>0 selected</span>
                    </div>

                    <div class="row g-3" data-perm-grid>
                        @foreach($permissions as $group => $perms)
                        <div class="col-xl-4 col-md-6" data-perm-module="{{ $group }}">
                            <div class="perm-module">
                                <div class="perm-module-head">
                                    <input class="form-check-input mt-0" type="checkbox" data-module-check
                                           id="{{ $mode }}_mod_{{ $loop->index }}">
                                    <label for="{{ $mode }}_mod_{{ $loop->index }}">{{ $group }}</label>
                                    <span class="perm-module-count" data-module-count></span>
                                </div>
                                @foreach($perms as $perm)
                                <div class="perm-row" data-perm-haystack="{{ Str::lower($group.' '.$perm->name.' '.$perm->description.' '.$perm->key) }}">
                                    <input class="form-check-input mt-1 perm-check" type="checkbox" name="permissions[]"
                                           value="{{ $perm->id }}" id="{{ $mode }}_perm_{{ $perm->id }}">
                                    <label for="{{ $mode }}_perm_{{ $perm->id }}">
                                        <span class="perm-name">{{ $perm->name }}</span>
                                        <span class="perm-desc">{{ $perm->description }}</span>
                                        <code>{{ $perm->key }}</code>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="perm-empty" data-perm-empty>Nothing matches that search.</div>
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

    // Wire the search, the per-module toggle, the bulk buttons and the counter on one form.
    document.querySelectorAll('[data-perm-form]').forEach(function (form) {
        const modules = Array.from(form.querySelectorAll('[data-perm-module]'));
        const boxes = Array.from(form.querySelectorAll('.perm-check'));
        const countEl = form.querySelector('[data-perm-count]');
        const emptyEl = form.querySelector('[data-perm-empty]');

        function refresh() {
            modules.forEach(function (module) {
                const inModule = Array.from(module.querySelectorAll('.perm-check'));
                const checked = inModule.filter(cb => cb.checked).length;
                const head = module.querySelector('[data-module-check]');
                head.checked = checked > 0 && checked === inModule.length;
                head.indeterminate = checked > 0 && checked < inModule.length;
                module.querySelector('[data-module-count]').textContent = checked + '/' + inModule.length;
            });
            const total = boxes.filter(cb => cb.checked).length;
            countEl.textContent = total + ' of ' + boxes.length + ' selected';
        }

        form.querySelectorAll('[data-module-check]').forEach(function (head) {
            head.addEventListener('change', function () {
                head.closest('[data-perm-module]').querySelectorAll('.perm-check').forEach(function (cb) {
                    if (!cb.disabled) { cb.checked = head.checked; }
                });
                refresh();
            });
        });

        boxes.forEach(cb => cb.addEventListener('change', refresh));

        form.querySelector('[data-perm-all]').addEventListener('click', function () {
            boxes.forEach(function (cb) { if (!cb.disabled) { cb.checked = true; } });
            refresh();
        });

        form.querySelector('[data-perm-none]').addEventListener('click', function () {
            boxes.forEach(function (cb) { if (!cb.disabled) { cb.checked = false; } });
            refresh();
        });

        form.querySelector('[data-perm-search]').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            modules.forEach(function (module) {
                let shown = 0;
                module.querySelectorAll('.perm-row').forEach(function (row) {
                    const hit = q === '' || row.dataset.permHaystack.includes(q);
                    row.style.display = hit ? '' : 'none';
                    if (hit) { shown++; }
                });
                module.style.display = shown > 0 ? '' : 'none';
                visible += shown;
            });
            emptyEl.style.display = visible === 0 ? 'block' : 'none';
        });

        form.refreshPermissions = refresh;
        refresh();
    });

    // Edit: pre-fill the role and tick what it already holds.
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
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
            form.querySelectorAll('[data-module-check]').forEach(cb => cb.disabled = isAdmin);
            form.querySelector('[data-admin-note]').classList.toggle('d-none', !isAdmin);
            form.refreshPermissions();
        });
    }

    document.querySelectorAll('.delete-role-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this role?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
