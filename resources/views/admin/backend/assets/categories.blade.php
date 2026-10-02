@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Asset Categories</h4>
                <small class="text-muted">Each category carries the depreciation basis its assets start from — a new asset inherits it and can still be changed.</small>
            </div>
            <div class="text-end">
                <a href="{{ route('assets.index') }}" class="btn btn-outline-primary me-2"><i class="ri-archive-2-line me-1"></i> Register</a>
                @can('asset-categories.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCategory">
                    <i class="ri-add-line me-1"></i> Add Category
                </button>
                @endcan
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th><th>Default method</th><th class="text-end">Basis</th>
                                <th>Status</th><th class="text-end">Assets</th><th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                            <tr>
                                <td class="fw-semibold">{{ $category->name }}</td>
                                <td>{{ $methods[$category->default_method] ?? $category->default_method }}</td>
                                <td class="text-end">
                                    @if($category->default_method === 'straight_line')
                                        {{ $category->default_useful_life_years ? $category->default_useful_life_years.' years' : '—' }}
                                    @elseif($category->default_method === 'reducing_balance')
                                        {{ $category->default_rate ? number_format($category->default_rate, 2).'% a year' : '—' }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}-subtle text-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end text-muted">{{ $category->assets_count }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <x-history-link :record="$category" as="button" />
                                        @can('asset-categories.edit')
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                                data-row="{{ json_encode($category->only(['id', 'name', 'default_method', 'default_useful_life_years', 'default_rate', 'is_active'])) }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        @endcan
                                        @can('asset-categories.delete')
                                        <form action="{{ route('asset.category.delete', $category->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-used="{{ $category->assets_count }}"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">None yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add --}}
@can('asset-categories.create')
<div class="modal fade" id="addCategory" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('asset.category.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Asset Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required placeholder="e.g. Vehicles or IT Equipment">

                    <label class="form-label mt-3">Default method <span class="text-danger">*</span></label>
                    <select class="form-control method-select" name="default_method" data-prefix="add" required>
                        @foreach($methods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>

                    <div class="mt-3" id="addLifeWrap">
                        <label class="form-label">Default useful life (years)</label>
                        <input type="number" min="1" max="100" class="form-control" name="default_useful_life_years" placeholder="5">
                    </div>

                    <div class="mt-3" id="addRateWrap">
                        <label class="form-label">Default rate (% a year)</label>
                        <input type="number" step="0.01" min="0.01" max="100" class="form-control" name="default_rate" placeholder="20.00">
                    </div>

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

{{-- Edit --}}
@endcan

@can('asset-categories.edit')
<div class="modal fade" id="editCategory" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Asset Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" data-field="name" required>

                    <label class="form-label mt-3">Default method <span class="text-danger">*</span></label>
                    <select class="form-control method-select" name="default_method" data-field="default_method" data-prefix="edit" required>
                        @foreach($methods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                    <small class="text-muted">Changing this only affects assets added from here on.</small>

                    <div class="mt-3" id="editLifeWrap">
                        <label class="form-label">Default useful life (years)</label>
                        <input type="number" min="1" max="100" class="form-control" name="default_useful_life_years" data-field="default_useful_life_years">
                    </div>

                    <div class="mt-3" id="editRateWrap">
                        <label class="form-label">Default rate (% a year)</label>
                        <input type="number" step="0.01" min="0.01" max="100" class="form-control" name="default_rate" data-field="default_rate">
                    </div>

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
    /**
     * A useful life belongs to straight line and a rate to reducing balance;
     * something that does not depreciate needs neither.
     */
    const syncMethod = select => {
        const prefix = select.dataset.prefix;
        document.getElementById(prefix + 'LifeWrap').classList.toggle('d-none', select.value !== 'straight_line');
        document.getElementById(prefix + 'RateWrap').classList.toggle('d-none', select.value !== 'reducing_balance');
    };

    document.querySelectorAll('.method-select').forEach(function (select) {
        syncMethod(select);
        select.addEventListener('change', () => syncMethod(select));
    });

    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', function () {
        const row = JSON.parse(btn.dataset.row);
        const form = document.getElementById('editForm');
        const method = form.querySelector('[data-field="default_method"]');

        form.action = '{{ url('asset-categories') }}/' + row.id;
        form.querySelector('[data-field="name"]').value = row.name || '';
        method.value = row.default_method || 'straight_line';
        form.querySelector('[data-field="default_useful_life_years"]').value = row.default_useful_life_years ?? '';
        form.querySelector('[data-field="default_rate"]').value = row.default_rate ?? '';
        form.querySelector('[data-field="is_active"]').checked = !!row.is_active;
        syncMethod(method);

        new bootstrap.Modal(document.getElementById('editCategory')).show();
    }));

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const used = parseInt(btn.dataset.used, 10) || 0;
        Swal.fire({
            title: 'Delete this category?',
            text: used > 0
                ? used + ' asset(s) will stay in the register but lose their category.'
                : 'No assets use this category.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
