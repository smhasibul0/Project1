@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Office Cost Types</h4>
                <small class="text-muted">Fixed costs recur every month (rent, internet, salary); variable ones are incurred as needed (repairs, stationery).</small>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-sm-end mt-2 mt-sm-0">
                <form action="{{ route('office.cost.types') }}" method="GET" class="d-flex flex-wrap gap-2">
                    <input type="search" class="form-control" name="q" value="{{ $search }}"
                           placeholder="Search name or category" style="min-width:240px">
                    <select class="form-select" name="nature" onchange="this.form.submit()" style="min-width:150px">
                        <option value="">All natures</option>
                        @foreach($natures as $option)
                            <option value="{{ $option }}" @selected($nature === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" title="Search"><i class="ri-search-line"></i></button>
                    @if($search !== '' || $nature !== '')
                        <a href="{{ route('office.cost.types') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
                @can('office.cost-types.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="ri-add-line me-1"></i> Add Cost Type
                </button>
                @endcan
            </div>
        </div>

        <div class="row g-3">
            {{-- The nature dropdown narrows the page to a single card, which then takes the full width. --}}
            @foreach(collect([['fixed', 'Fixed — monthly', $fixedTypes, 'primary'], ['variable', 'Variable — as incurred', $variableTypes, 'info']])->filter(fn ($card) => $nature === '' || $nature === $card[0]) as [$natureKey, $heading, $rows, $tone])
            <div class="col-lg-{{ $nature === '' ? 6 : 12 }}">
                <div class="card h-100">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0 fs-15"><i class="ri-price-tag-3-line me-1 text-{{ $tone }}"></i>{{ $heading }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr><th>Name</th><th>Category</th><th class="text-end">Monthly</th><th>Status</th><th class="text-end">Used</th><th class="text-end">Action</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $type)
                                    <tr>
                                        <td class="fw-semibold">{{ $type->name }}</td>
                                        <td>
                                            @if($type->categoryName())
                                                {{ $type->categoryName() }}
                                                @if($type->subCategoryName())<span class="text-muted"> · {{ $type->subCategoryName() }}</span>@endif
                                            @else
                                                <span class="text-muted">Uncategorised</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($type->isFixed() && $type->monthly_amount > 0)
                                                ৳ {{ number_format($type->monthly_amount, 2) }}
                                            @elseif($type->isFixed())
                                                <span class="text-muted" title="Set an amount to include this in monthly generation">not set</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-{{ $type->is_active ? 'success' : 'secondary' }}-subtle text-{{ $type->is_active ? 'success' : 'secondary' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td class="text-end text-muted">{{ $type->expenses_count }}</td>
                                        <td class="text-end">
                                            <div class="d-flex gap-1 justify-content-end">
                                                @can('office.cost-types.edit')
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-row="{{ json_encode($type->only(['id', 'name', 'expense_category_id', 'nature', 'monthly_amount', 'is_active']) + ['category_parent_id' => $type->category?->parent_id ?? $type->expense_category_id]) }}"><i class="ri-edit-line"></i></button>
                                                @endcan
                                                @can('office.cost-types.delete')
                                                <form action="{{ route('office.cost.type.delete', $type->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-used="{{ $type->expenses_count }}"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">{{ $search === '' ? 'None yet.' : 'Nothing matches “'.$search.'”.' }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Add modal --}}
@can('office.cost-types.create')
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('office.cost.type.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Cost Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required placeholder="e.g. Office Rent or Stationery">

                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <select class="form-control category-select" data-sub="addSubSelect">
                                <option value="">-- None --</option>
                                @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sub-category</label>
                            <select class="form-control" name="expense_category_id" id="addSubSelect">
                                <option value="">-- Select category first --</option>
                            </select>
                        </div>
                    </div>
                    <small class="text-muted">Every expense booked against this type reports under this category.@can('expense-categories.view') <a href="{{ route('expense.categories') }}" target="_blank">Manage categories</a>@endcan</small>

                    <label class="form-label mt-3">Nature <span class="text-danger">*</span></label>
                    <select class="form-control nature-select" name="nature" data-monthly="addMonthlyWrap" required>
                        <option value="fixed">Fixed — recurs every month</option>
                        <option value="variable" selected>Variable — incurred as needed</option>
                    </select>
                    <small class="text-muted">Every expense booked against this type inherits its nature.</small>

                    <div class="mt-3 d-none" id="addMonthlyWrap">
                        <label class="form-label">Standard monthly amount</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="monthly_amount" placeholder="0.00">
                        <small class="text-muted">Used by "Generate month" on the expenses page. Leave empty to keep this type out of generation.</small>
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

@endcan

{{-- Edit modal --}}
@can('office.cost-types.edit')
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cost Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" data-field="name" required>

                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <select class="form-control category-select" data-field="category" data-sub="editSubSelect">
                                <option value="">-- None --</option>
                                @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sub-category</label>
                            <select class="form-control" name="expense_category_id" data-field="expense_category_id" id="editSubSelect">
                                <option value="">-- Select category first --</option>
                            </select>
                        </div>
                    </div>

                    <label class="form-label mt-3">Nature <span class="text-danger">*</span></label>
                    <select class="form-control nature-select" name="nature" data-field="nature" data-monthly="editMonthlyWrap" required>
                        <option value="fixed">Fixed — recurs every month</option>
                        <option value="variable">Variable — incurred as needed</option>
                    </select>
                    <small class="text-muted">Changing this re-classifies every expense already booked against the type.</small>

                    <div class="mt-3 d-none" id="editMonthlyWrap">
                        <label class="form-label">Standard monthly amount</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="monthly_amount" data-field="monthly_amount" placeholder="0.00">
                        <small class="text-muted">Switching to Variable clears this.</small>
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
    // Sub-categories per top-level category, for the cascading selects.
    const children = @json($categories->mapWithKeys(fn ($c) => [$c->id => $c->children->map(fn ($ch) => ['id' => $ch->id, 'name' => $ch->name])->values()]));

    /**
     * Refill a sub-category select for the chosen category. Picking the
     * category alone is valid — "(General)" files the type against the
     * category itself, so a category with no children is still usable.
     */
    const fillSubs = (categorySelect, selectedId) => {
        const sub = document.getElementById(categorySelect.dataset.sub);
        const pid = categorySelect.value;
        if (!pid) {
            sub.innerHTML = '<option value="">-- None --</option>';
            return;
        }
        const options = ['<option value="' + pid + '">(General)</option>'];
        (children[pid] || []).forEach(c => options.push('<option value="' + c.id + '">' + c.name + '</option>'));
        sub.innerHTML = options.join('');
        if (selectedId) { sub.value = selectedId; }
    };

    document.querySelectorAll('.category-select').forEach(sel => {
        sel.addEventListener('change', () => fillSubs(sel));
    });

    // The monthly amount only applies to fixed types, so it follows the nature select.
    const syncMonthly = sel => {
        document.getElementById(sel.dataset.monthly).classList.toggle('d-none', sel.value !== 'fixed');
    };
    document.querySelectorAll('.nature-select').forEach(sel => {
        syncMonthly(sel);
        sel.addEventListener('change', () => syncMonthly(sel));
    });

    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', function () {
        const r = JSON.parse(btn.dataset.row);
        const form = document.getElementById('editForm');
        const nature = form.querySelector('[data-field="nature"]');
        form.action = '{{ url('office-cost-types') }}/' + r.id;
        form.querySelector('[data-field="name"]').value = r.name || '';
        nature.value = r.nature || 'variable';
        form.querySelector('[data-field="monthly_amount"]').value = r.monthly_amount ?? '';
        // A type stores only the sub-category; its parent drives the first select.
        const categorySelect = form.querySelector('[data-field="category"]');
        categorySelect.value = r.category_parent_id ?? '';
        fillSubs(categorySelect, r.expense_category_id ?? '');
        form.querySelector('[data-field="is_active"]').checked = !!r.is_active;
        syncMonthly(nature);
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }));

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const used = parseInt(btn.dataset.used, 10) || 0;
        Swal.fire({
            title: 'Delete this cost type?',
            text: used > 0
                ? used + ' recorded expense(s) will stay, but lose their type and count as variable.'
                : 'This cost type is not used by any expense.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
