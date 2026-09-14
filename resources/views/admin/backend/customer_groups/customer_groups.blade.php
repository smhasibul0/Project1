@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Customer Groups</h4>
                <small class="text-muted">Price tiers &amp; discounts for customers</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Customer Groups</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Customer Groups</h5>
                @can('customer-groups.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                    <i class="ri-add-line me-1"></i> Add Group
                </button>
                @endcan
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Discount</th>
                                <th>Customers</th>
                                <th>Description</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groups as $index => $group)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $group->name }}</td>
                                <td>
                                    @if($group->discount_type === 'percentage')
                                        {{ rtrim(rtrim(number_format($group->discount_amount, 2), '0'), '.') }}%
                                    @else
                                        ৳ {{ number_format($group->discount_amount, 2) }}
                                    @endif
                                </td>
                                <td>{{ $group->contacts_count }}</td>
                                <td>{{ $group->description ?: '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        @can('customer-groups.edit')
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-group-btn"
                                                data-bs-toggle="modal" data-bs-target="#editGroupModal"
                                                data-group="{{ json_encode($group) }}" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        @endcan
                                        @can('customer-groups.delete')
                                        <form action="{{ route('customer.group.delete', $group->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-group-btn" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No customer groups found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== ADD / EDIT MODALS ===================== --}}
@php
    // Only build the dialogs this role is allowed to submit.
    $groupModals = array_filter([
        'add' => auth()->user()->can('customer-groups.create') ? 'addGroupModal' : null,
        'edit' => auth()->user()->can('customer-groups.edit') ? 'editGroupModal' : null,
    ]);
@endphp

@foreach($groupModals as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $mode }}GroupForm"
                  action="{{ $mode === 'add' ? route('customer.group.store') : url('customer-groups') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif

                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Customer Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" data-field="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discount Type</label>
                        <select class="form-control" name="discount_type" data-field="discount_type">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed (৳)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discount Amount</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="discount_amount" data-field="discount_amount" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" data-field="description" rows="2"></textarea>
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

    const editModal = document.getElementById('editGroupModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const g = JSON.parse(e.relatedTarget.dataset.group);
            const form = document.getElementById('editGroupForm');
            form.action = '{{ url('customer-groups') }}/' + g.id;
            form.querySelectorAll('[data-field]').forEach(function (field) {
                field.value = (g[field.dataset.field] ?? '') === null ? '' : (g[field.dataset.field] ?? '');
            });
        });
    }

    document.querySelectorAll('.delete-group-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = btn.closest('form');
            Swal.fire({
                title: 'Delete this group?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#ef4444',
            }).then(function (result) {
                if (result.isConfirmed) { form.submit(); }
            });
        });
    });

});
</script>

@endsection
