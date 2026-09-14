@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Customers</h4>
                <small class="text-muted">The people and firms whose goods you ship</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Customers</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Customer List</h5>
                @can('customers.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addContactModal">
                    <i class="ri-add-line me-1"></i> Add Customer
                </button>
                @endcan
            </div>

            <div class="card-body p-0">

                <x-data-table id="contactsTable" export-name="customers">
                    <table id="contactsTable" class="ct-table" style="min-width: 1500px;">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Customer ID</th>
                                <th>Shipping Mark</th>
                                <th>Added On</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th class="text-end">Credit Limit</th>
                                <th>Pay Term</th>
                                <th class="text-end">Advance Balance</th>
                                <th class="text-end">Total Sale Due</th>
                                <th>Lead By</th>
                                <th>Remarks</th>
                                <th data-filter="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody id="contactsBody">
                            @foreach($contacts as $contact)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Payments">
                                                    <i class="ri-money-dollar-circle-line me-2"></i>Pay
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewContactModal" data-contact="{{ json_encode($contact) }}">
                                                    <i class="ri-eye-line me-2"></i>View
                                                </button>
                                            </li>
                                            @can('customers.edit')
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editContactModal" data-contact="{{ json_encode($contact) }}">
                                                    <i class="ri-edit-line me-2"></i>Edit
                                                </button>
                                            </li>
                                            @endcan
                                            <li>
                                                @if($contact->user)
                                                    <span class="dropdown-item text-success" style="cursor:default;"><i class="ri-user-follow-line me-2"></i>Login: {{ $contact->user->email }}</span>
                                                @else
                                                    @can('customers.create-login')
                                                    <button type="button" class="dropdown-item create-login-btn" data-bs-toggle="modal" data-bs-target="#createLoginModal"
                                                            data-id="{{ $contact->id }}"
                                                            data-name="{{ $contact->name ?: $contact->business_name }}"
                                                            data-email="{{ $contact->email }}">
                                                        <i class="ri-user-add-line me-2"></i>Create Login
                                                    </button>
                                                    @endcan
                                                @endif
                                            </li>
                                            @can('customers.toggle')
                                            <li>
                                                <form action="{{ route('contact.toggle', $contact->id) }}" method="POST" class="m-0">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-shut-down-line me-2"></i>{{ $contact->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                            @can('customers.delete')
                                            <li>
                                                <form action="{{ route('contact.delete', $contact->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-contact-btn">
                                                        <i class="ri-delete-bin-line me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Ledger">
                                                    <i class="ri-book-2-line me-2"></i>Ledger
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Shipments">
                                                    <i class="ri-ship-line me-2"></i>Shipments
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Documents &amp; Note">
                                                    <i class="ri-attachment-line me-2"></i>Documents &amp; Note
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $contact->contact_code ?: '—' }}</span></td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $contact->shipping_mark ?: '—' }}</span></td>
                                <td>{{ $contact->created_at?->format('d M Y') }}</td>
                                <td>{{ $contact->name }}</td>
                                <td>{{ collect([$contact->address_line_1, $contact->city, $contact->country])->filter()->implode(', ') ?: '—' }}</td>
                                <td>{{ $contact->mobile ?: '—' }}</td>
                                <td>{{ $contact->email ?: '—' }}</td>
                                <td class="text-end">{{ $contact->credit_limit !== null ? '৳ '.number_format($contact->credit_limit, 2) : 'No Limit' }}</td>
                                <td>{{ $contact->pay_term_number ? $contact->pay_term_number.' '.ucfirst($contact->pay_term_type) : '—' }}</td>
                                <td class="text-end">৳ {{ number_format($contact->advance_balance, 2) }}</td>
                                <td class="text-end">৳ 0.00</td>
                                <td>{{ $contact->lead_by ?: '—' }}</td>
                                <td>{{ $contact->notes ?: '—' }}</td>
                                <td>
                                    @if($contact->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
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

{{-- ===================== ADD / EDIT MODALS ===================== --}}
@php
    // Only build the dialogs this role is allowed to submit.
    $contactModals = array_filter([
        'add' => auth()->user()->can('customers.create') ? 'addContactModal' : null,
        'edit' => auth()->user()->can('customers.edit') ? 'editContactModal' : null,
    ]);
@endphp

@foreach($contactModals as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="{{ $mode }}ContactForm"
                  action="{{ $mode === 'add' ? route('contact.store') : url('contacts') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif

                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3" style="max-height: 65vh; overflow-y: auto;">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" data-field="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Business Name</label>
                        <input type="text" class="form-control" name="business_name" data-field="business_name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Shipping Mark</label>
                        <input type="text" class="form-control shipping-mark-input" name="shipping_mark" data-field="shipping_mark"
                               placeholder="filled in from the name">
                        <small class="text-muted shipping-mark-hint">Suggested from the customer's name — edit it if you prefer another.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tax / VAT Number</label>
                        <input type="text" class="form-control" name="tax_number" data-field="tax_number">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mobile</label>
                        <input type="text" class="form-control" name="mobile" data-field="mobile">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Alternate Contact</label>
                        <input type="text" class="form-control" name="alternate_contact" data-field="alternate_contact">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" data-field="email">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" class="form-control" name="opening_balance" data-field="opening_balance" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer Group</label>
                        <select class="form-control" name="customer_group_id" data-field="customer_group_id">
                            <option value="">-- None --</option>
                            @foreach($customerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Credit Limit</label>
                        <input type="number" step="0.01" class="form-control" name="credit_limit" data-field="credit_limit" placeholder="Leave blank for no limit">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pay Term</label>
                        <input type="number" min="0" class="form-control" name="pay_term_number" data-field="pay_term_number" placeholder="e.g. 30">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pay Term Type</label>
                        <select class="form-control" name="pay_term_type" data-field="pay_term_type">
                            <option value="">--</option>
                            <option value="days">Days</option>
                            <option value="months">Months</option>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Address</small></div>
                    <div class="col-md-6">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" class="form-control" name="address_line_1" data-field="address_line_1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" class="form-control" name="address_line_2" data-field="address_line_2">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" data-field="city">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State</label>
                        <input type="text" class="form-control" name="state" data-field="state">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Zip Code</label>
                        <input type="text" class="form-control" name="zip_code" data-field="zip_code">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country</label>
                        <input type="text" class="form-control" name="country" data-field="country">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Lead By</label>
                        <input type="text" class="form-control" name="lead_by" data-field="lead_by" placeholder="Who brought this lead">
                    </div>
                    <div class="col-12">
                        <label class="form-label">More Information</label>
                        <textarea class="form-control" name="more_information" data-field="more_information" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="notes" data-field="notes" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
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

{{-- ===================== VIEW MODAL ===================== --}}
<div class="modal fade" id="viewContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th style="width:38%">Customer ID</th><td data-view="contact_code"></td></tr>
                        <tr><th>Shipping Mark</th><td data-view="shipping_mark"></td></tr>
                        <tr><th>Name</th><td data-view="name"></td></tr>
                        <tr><th>Business Name</th><td data-view="business_name"></td></tr>
                        <tr><th>Email</th><td data-view="email"></td></tr>
                        <tr><th>Mobile</th><td data-view="mobile"></td></tr>
                        <tr><th>Alternate Contact</th><td data-view="alternate_contact"></td></tr>
                        <tr><th>Tax / VAT Number</th><td data-view="tax_number"></td></tr>
                        <tr><th>Opening Balance</th><td data-view="opening_balance"></td></tr>
                        <tr><th>Credit Limit</th><td data-view="credit_limit"></td></tr>
                        <tr><th>Pay Term</th><td data-view="_pay_term"></td></tr>
                        <tr><th>Advance Balance</th><td data-view="advance_balance"></td></tr>
                        <tr><th>Address</th><td data-view="_address"></td></tr>
                        <tr><th>More Information</th><td data-view="more_information"></td></tr>
                        <tr><th>Lead By</th><td data-view="lead_by"></td></tr>
                        <tr><th>Remarks</th><td data-view="notes"></td></tr>
                    </tbody>
                </table>

                <h6 class="fw-semibold mt-3 mb-2"><i class="ri-file-list-3-line me-1"></i>Quotation Requests</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="vcRequests">
                        <thead><tr><th>No</th><th>Requested</th><th>Status</th><th class="text-end">Quoted Total</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Create Login modal ===================== --}}
@can('customers.create-login')
<div class="modal fade" id="createLoginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="createLoginForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Portal Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Create a login for <strong id="clName">—</strong> so they can access the customer portal.</p>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="clUsername" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="clEmail" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Share this with the customer. Minimum 6 characters.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create Login</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ----- Shipping mark: suggest one from the name, leave it editable -----
    // The suggestion is asked of the server because uniqueness is a database question.
    document.querySelectorAll('#addContactForm, #editContactForm').forEach(function (form) {
        const mark = form.querySelector('.shipping-mark-input');
        const name = form.querySelector('[data-field="name"]');
        const business = form.querySelector('[data-field="business_name"]');
        const id = () => (form.action.match(/\/(\d+)$/) || [])[1] || '';
        let timer = null;

        // Once it's typed in by hand, stop proposing over the top of it.
        mark.addEventListener('input', function () { mark.dataset.touched = '1'; });

        function suggest() {
            if (mark.dataset.touched || (!name.value.trim() && !business.value.trim())) { return; }

            clearTimeout(timer);
            timer = setTimeout(async function () {
                const query = new URLSearchParams({
                    name: name.value, business_name: business.value, ignore: id(),
                });
                try {
                    const response = await fetch('{{ route('customer.shipping.mark') }}?' + query, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    if (!mark.dataset.touched) { mark.value = data.shipping_mark; }
                } catch (e) {
                    // No suggestion is no problem — the field can be filled in by hand.
                }
            }, 300);
        }

        [name, business].forEach(field => field.addEventListener('input', suggest));
    });

    // Create-login modal: fill the form from the clicked customer row. Only rendered for
    // roles that may issue a portal login.
    const createLoginModal = document.getElementById('createLoginModal');
    if (createLoginModal) {
        createLoginModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            if (!btn) { return; }
            document.getElementById('createLoginForm').action = '{{ url('customers') }}/' + btn.dataset.id + '/create-login';
            document.getElementById('clName').textContent = btn.dataset.name || 'this customer';
            document.getElementById('clEmail').value = btn.dataset.email || '';
            document.getElementById('clUsername').value = btn.dataset.email || '';
        });
    }

    // Render Actions dropdowns with a fixed Popper strategy so the responsive-table
    // overflow doesn't clip the open menu.
    document.querySelectorAll('#contactsTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    // ----- Edit modal: pre-fill from the row's JSON blob -----
    const editContactModal = document.getElementById('editContactModal');
    if (editContactModal) {
        editContactModal.addEventListener('show.bs.modal', function (e) {
            const c = JSON.parse(e.relatedTarget.dataset.contact);
            const form = document.getElementById('editContactForm');
            form.action = '{{ url('contacts') }}/' + c.id;

            form.querySelectorAll('[data-field]').forEach(function (field) {
                const key = field.dataset.field;
                if (field.type === 'checkbox') {
                    field.checked = !!Number(c[key]);
                } else {
                    field.value = (c[key] ?? '') === null ? '' : (c[key] ?? '');
                }
            });

            // A customer who already has a mark keeps it; renaming them won't move it.
            const mark = form.querySelector('.shipping-mark-input');
            if (c.shipping_mark) { mark.dataset.touched = '1'; } else { delete mark.dataset.touched; }
        });
    }

    // A fresh Add form starts open to suggestions again (only rendered if the role may add one).
    document.getElementById('addContactModal')?.addEventListener('show.bs.modal', function () {
        delete document.querySelector('#addContactForm .shipping-mark-input').dataset.touched;
    });

    // ----- View modal: read-only details -----
    const viewModal = document.getElementById('viewContactModal');
    viewModal.addEventListener('show.bs.modal', function (e) {
        const c = JSON.parse(e.relatedTarget.dataset.contact);
        viewModal.querySelectorAll('[data-view]').forEach(function (cell) {
            const key = cell.dataset.view;
            let val;
            if (key === '_address') {
                val = [c.address_line_1, c.address_line_2, c.city, c.state, c.zip_code, c.country].filter(Boolean).join(', ');
            } else if (key === '_pay_term') {
                val = c.pay_term_number ? c.pay_term_number + ' ' + (c.pay_term_type || '') : '';
            } else {
                val = c[key];
            }
            cell.textContent = (val === null || val === undefined || val === '') ? '—' : val;
        });

        // Quotation requests (portal + admin quotations for this customer)
        const reqBody = viewModal.querySelector('#vcRequests tbody');
        const rows = (c.quotations || []);
        const badge = { draft: 'secondary', requested: 'warning', quoted: 'info', accepted: 'success', negotiating: 'primary', rejected: 'danger', converted: 'dark' };
        reqBody.innerHTML = rows.length ? rows.map(function (q) {
            const total = Number(q.grand_total) > 0 ? '৳' + Number(q.grand_total).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—';
            const d = (q.query_received_date || q.created_at || '').substring(0, 10);
            return '<tr><td>' + q.quotation_no + '</td><td>' + d + '</td>'
                + '<td><span class="badge bg-' + (badge[q.status] || 'secondary') + ' text-capitalize">' + q.status + '</span></td>'
                + '<td class="text-end">' + total + '</td>'
                + '<td class="text-end"><a href="{{ url('quotations') }}/' + q.id + '" class="btn btn-sm btn-outline-secondary py-0"><i class="ri-eye-line"></i></a></td></tr>';
        }).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">No requests yet.</td></tr>';
    });

    // Delegated so handlers survive table paging / row reordering.
    document.addEventListener('click', function (e) {
        // ----- Delete: confirm then submit -----
        const del = e.target.closest('.delete-contact-btn');
        if (del) {
            const form = del.closest('form');
            Swal.fire({
                title: 'Delete this customer?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#ef4444',
            }).then(function (result) {
                if (result.isConfirmed) { form.submit(); }
            });
            return;
        }

        // ----- Not-yet-built menu items -----
        const soon = e.target.closest('.coming-soon');
        if (soon) {
            e.preventDefault();
            Swal.fire({
                icon: 'info',
                title: soon.dataset.feature + ' — coming soon',
                text: 'This will be available in a later phase.',
                confirmButtonColor: '#6366f1',
            });
        }
    });

});
</script>

@endsection
