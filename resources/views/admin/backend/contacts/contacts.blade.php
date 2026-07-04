@extends('admin.admin_master')
@section('admin')

@php $isCustomer = $type === 'customer'; @endphp

{{-- Self-contained table toolbar (entries / search / export / print / pagination),
     modelled on the Payment Accounts account-book page. No jQuery/DataTables dependency. --}}
<style>
    .ct-controls { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.85rem 1rem; border-bottom:1px solid #e2e8f0; }
    .ct-controls-left { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; font-size:.82rem; color:#64748b; }
    .ct-controls-left select { border:1px solid #cbd5e1; border-radius:6px; padding:.3rem .5rem; font-size:.82rem; color:#334155; outline:none; cursor:pointer; }
    .ct-btn { border:1px solid #d6c9f7; background:#fff; color:#7c5cf0; border-radius:6px; padding:.38rem .8rem; font-size:.82rem; font-weight:500; display:inline-flex; align-items:center; gap:.35rem; cursor:pointer; transition:background-color .15s ease,color .15s ease,border-color .15s ease; white-space:nowrap; }
    .ct-btn:hover, .ct-btn:focus { background:#7c5cf0; border-color:#7c5cf0; color:#fff; }
    .ct-search-wrap { position:relative; }
    .ct-search-wrap i { position:absolute; left:.7rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.85rem; pointer-events:none; }
    .ct-search { border:1px solid #d6c9f7; border-radius:6px; padding:.42rem .8rem .42rem 2.1rem; font-size:.82rem; color:#334155; width:230px; outline:none; transition:border-color .2s; }
    .ct-search:focus { border-color:#7c5cf0; }
    .ct-search::placeholder { color:#94a3b8; }
    .ct-scroll-area { overflow-x:auto; scrollbar-width:thin; scrollbar-color:#cbd5e1 transparent; }
    .ct-scroll-area::-webkit-scrollbar { height:6px; }
    .ct-scroll-area::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:99px; }
    .ct-scroll-area::-webkit-scrollbar-thumb:hover { background:#7c5cf0; }
    #contactsTable { width:100%; border-collapse:collapse; font-size:.82rem; margin-bottom:0; }
    #contactsTable thead th { background:#f8fafc; color:#475569; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.6rem .9rem; white-space:nowrap; border-bottom:2px solid #e2e8f0; border-right:1px solid #e2e8f0; user-select:none; }
    #contactsTable thead th:last-child { border-right:none; }
    #contactsTable thead th.sortable { cursor:pointer; }
    #contactsTable thead th.sortable:hover { background:#f1f5f9; color:#1e293b; }
    #contactsTable thead th.sort-asc::after { content:' ↑'; color:#7c5cf0; }
    #contactsTable thead th.sort-desc::after { content:' ↓'; color:#7c5cf0; }
    #contactsTable tbody tr { border-bottom:1px solid #e2e8f0; }
    #contactsTable tbody tr:nth-child(even) { background:#fbfaff; }
    #contactsTable tbody tr:hover { background:#f4f0fe; }
    #contactsTable tbody td { padding:.55rem .9rem; color:#334155; border-right:1px solid #e2e8f0; vertical-align:middle; white-space:nowrap; }
    #contactsTable tbody td:last-child { border-right:none; }
    .ct-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; padding:.8rem 1rem; border-top:1px solid #e2e8f0; }
    .ct-info { font-size:.78rem; color:#64748b; }
    .ct-pagination { display:flex; gap:.25rem; flex-wrap:wrap; }
    .ct-pagination button { border:1px solid #cbd5e1; background:#fff; color:#475569; font-size:.78rem; padding:.3rem .65rem; border-radius:6px; cursor:pointer; min-width:32px; transition:all .15s; }
    .ct-pagination button:hover:not(:disabled) { border-color:#7c5cf0; color:#7c5cf0; }
    .ct-pagination button.active { background:#7c5cf0; border-color:#7c5cf0; color:#fff; font-weight:600; }
    .ct-pagination button:disabled { opacity:.35; cursor:default; }
    .ct-no-results { text-align:center; padding:2.5rem 1rem; color:#94a3b8; font-size:.85rem; }
</style>

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">{{ $pageTitle }}</h4>
                <small class="text-muted">Manage your {{ strtolower($pageTitle) }}</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ $pageTitle }} List</h5>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addContactModal">
                    <i class="ri-add-line me-1"></i> Add {{ Str::singular($pageTitle) }}
                </button>
            </div>

            <div class="card-body p-0">

                {{-- Toolbar --}}
                <div class="ct-controls">
                    <div class="ct-controls-left">
                        Show
                        <select id="ctPageSize">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                            <option value="all">All</option>
                        </select>
                        entries
                        <button class="ct-btn ms-1" id="ctExportCsv"><i class="ri-file-text-line"></i> Export CSV</button>
                        <button class="ct-btn" id="ctExportExcel"><i class="ri-file-excel-2-line"></i> Export Excel</button>
                        <button class="ct-btn" id="ctPrint"><i class="ri-printer-line"></i> Print</button>
                        <div class="dropdown d-inline-block">
                            <button class="ct-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="ri-eye-line"></i> Column visibility</button>
                            <ul class="dropdown-menu p-1" id="ctColvisMenu" style="max-height:320px; overflow:auto;"></ul>
                        </div>
                        <button class="ct-btn" id="ctExportPdf"><i class="ri-file-pdf-2-line"></i> Export PDF</button>
                    </div>
                    <div class="ct-search-wrap">
                        <i class="ri-search-line"></i>
                        <input class="ct-search" id="ctSearch" type="text" placeholder="Search ...">
                    </div>
                </div>

                <div class="ct-scroll-area">
                    <table id="contactsTable" style="min-width: {{ $isCustomer ? '1500px' : '2100px' }};">
                        <thead>
                            <tr>
                                <th>Action</th>
                                @if($isCustomer)
                                    <th>Customer ID</th>
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
                                @else
                                    <th>Supplier ID</th>
                                    <th>Business Name</th>
                                    <th>Address</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Bank Details</th>
                                    <th>Country</th>
                                    <th>Warehouse Address</th>
                                    <th>Shipping Mark</th>
                                    <th>More Info</th>
                                    <th>Remarks</th>
                                    <th class="text-end">Credit Limit</th>
                                    <th>Pay Term</th>
                                    <th class="text-end">Opening Balance</th>
                                    <th class="text-end">Advance Balance</th>
                                    <th class="text-end">Total Purchase Due</th>
                                @endif
                                <th>Status</th>
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
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editContactModal" data-contact="{{ json_encode($contact) }}">
                                                    <i class="ri-edit-line me-2"></i>Edit
                                                </button>
                                            </li>
                                            <li>
                                                <form action="{{ route('contact.toggle', $contact->id) }}" method="POST" class="m-0">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-shut-down-line me-2"></i>{{ $contact->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form action="{{ route('contact.delete', $contact->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-contact-btn">
                                                        <i class="ri-delete-bin-line me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Ledger">
                                                    <i class="ri-book-2-line me-2"></i>Ledger
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="{{ $isCustomer ? 'Sales' : 'Purchases' }}">
                                                    <i class="ri-{{ $isCustomer ? 'shopping-cart-2' : 'shopping-bag' }}-line me-2"></i>{{ $isCustomer ? 'Sales' : 'Purchases' }}
                                                </a>
                                            </li>
                                            @unless($isCustomer)
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Stock Report">
                                                    <i class="ri-bar-chart-box-line me-2"></i>Stock Report
                                                </a>
                                            </li>
                                            @endunless
                                            <li>
                                                <a class="dropdown-item coming-soon" href="#" data-feature="Documents &amp; Note">
                                                    <i class="ri-attachment-line me-2"></i>Documents &amp; Note
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                @if($isCustomer)
                                    <td><span class="badge bg-light text-dark">{{ $contact->contact_code ?: '—' }}</span></td>
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
                                @else
                                    <td><span class="badge bg-light text-dark">{{ $contact->contact_code ?: '—' }}</span></td>
                                    <td>{{ $contact->business_name ?: $contact->name }}</td>
                                    <td>{{ Str::limit(collect([$contact->address_line_1, $contact->city, $contact->country])->filter()->implode(', '), 30) ?: '—' }}</td>
                                    <td>{{ $contact->mobile ?: '—' }}</td>
                                    <td>{{ $contact->email ?: '—' }}</td>
                                    <td>{{ Str::limit($contact->bank_details, 25) ?: '—' }}</td>
                                    <td>{{ $contact->country ?: '—' }}</td>
                                    <td>{{ Str::limit($contact->warehouse_address, 25) ?: '—' }}</td>
                                    <td>{{ $contact->shipping_mark ?: '—' }}</td>
                                    <td>{{ Str::limit($contact->more_information, 25) ?: '—' }}</td>
                                    <td>{{ Str::limit($contact->notes, 25) ?: '—' }}</td>
                                    <td class="text-end">{{ $contact->credit_limit !== null ? '৳ '.number_format($contact->credit_limit, 2) : 'No Limit' }}</td>
                                    <td>{{ $contact->pay_term_number ? $contact->pay_term_number.' '.ucfirst($contact->pay_term_type) : '—' }}</td>
                                    <td class="text-end">৳ {{ number_format($contact->opening_balance, 2) }}</td>
                                    <td class="text-end">৳ {{ number_format($contact->advance_balance, 2) }}</td>
                                    <td class="text-end">৳ 0.00</td>
                                @endif
                                <td>
                                    @if($contact->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                    @if($contact->type === 'both')
                                        <span class="badge bg-info">Both</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer --}}
                <div class="ct-footer">
                    <div class="ct-info" id="ctInfo">Loading…</div>
                    <div class="ct-pagination" id="ctPagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== ADD / EDIT MODALS ===================== --}}
@foreach(['add' => 'addContactModal', 'edit' => 'editContactModal'] as $mode => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="{{ $mode }}ContactForm"
                  action="{{ $mode === 'add' ? route('contact.store') : url('contacts') }}" method="POST">
                @csrf
                @if($mode === 'edit') @method('PUT') @endif

                <div class="modal-header">
                    <h5 class="modal-title">{{ ucfirst($mode) }} {{ Str::singular($pageTitle) }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3" style="max-height: 65vh; overflow-y: auto;">
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select class="form-control" name="type" data-field="type">
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            <option value="both">Both (Supplier &amp; Customer)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name @if($isCustomer)<span class="text-danger">*</span>@endif</label>
                        <input type="text" class="form-control" name="name" data-field="name" {{ $isCustomer ? 'required' : '' }}>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Business Name @unless($isCustomer)<span class="text-danger">*</span>@endunless</label>
                        <input type="text" class="form-control" name="business_name" data-field="business_name" {{ $isCustomer ? '' : 'required' }}>
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

                    @if($isCustomer)
                    <div class="col-md-6">
                        <label class="form-label">Customer Group</label>
                        <select class="form-control" name="customer_group_id" data-field="customer_group_id">
                            <option value="">-- None --</option>
                            @foreach($customerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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

                    @unless($isCustomer)
                    <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Supplier / Import Details</small></div>
                    <div class="col-12">
                        <label class="form-label">Bank Details</label>
                        <textarea class="form-control" name="bank_details" data-field="bank_details" rows="2" placeholder="Bank name, account no, SWIFT, LC details…"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse Address</label>
                        <textarea class="form-control" name="warehouse_address" data-field="warehouse_address" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Shipping Mark</label>
                        <input type="text" class="form-control" name="shipping_mark" data-field="shipping_mark">
                    </div>
                    <div class="col-12">
                        <label class="form-label">More Information</label>
                        <textarea class="form-control" name="more_information" data-field="more_information" rows="2"></textarea>
                    </div>
                    @endunless

                    <div class="col-md-6">
                        <label class="form-label">Lead By</label>
                        <input type="text" class="form-control" name="lead_by" data-field="lead_by" placeholder="Who brought this lead">
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
                <h5 class="modal-title">{{ Str::singular($pageTitle) }} Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th style="width:38%">{{ $isCustomer ? 'Customer' : 'Supplier' }} ID</th><td data-view="contact_code"></td></tr>
                        <tr><th>Type</th><td data-view="type"></td></tr>
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
                        @unless($isCustomer)
                        <tr><th>Bank Details</th><td data-view="bank_details"></td></tr>
                        <tr><th>Warehouse Address</th><td data-view="warehouse_address"></td></tr>
                        <tr><th>Shipping Mark</th><td data-view="shipping_mark"></td></tr>
                        <tr><th>More Information</th><td data-view="more_information"></td></tr>
                        @endunless
                        <tr><th>Lead By</th><td data-view="lead_by"></td></tr>
                        <tr><th>Remarks</th><td data-view="notes"></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

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
    document.getElementById('editContactModal').addEventListener('show.bs.modal', function (e) {
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
    });

    // Delegated so handlers survive table paging / row reordering.
    document.addEventListener('click', function (e) {
        // ----- Delete: confirm then submit -----
        const del = e.target.closest('.delete-contact-btn');
        if (del) {
            const form = del.closest('form');
            Swal.fire({
                title: 'Delete this contact?',
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

    // ===================== Table toolbar: entries / search / sort / paging / export =====================
    (function () {
        const table = document.getElementById('contactsTable');
        const tbody = document.getElementById('contactsBody');
        if (!table || !tbody) { return; }

        const allRows = Array.from(tbody.querySelectorAll('tr'));
        const totalRows = allRows.length;
        const headers = Array.from(table.querySelectorAll('thead th'));
        const colCount = headers.length;
        let filtered = allRows.slice();
        let currentPage = 1;
        let pageSize = 100;
        let sortCol = -1, sortDir = 1;

        const infoEl = document.getElementById('ctInfo');
        const pagEl = document.getElementById('ctPagination');

        const cellText = (row, col) => {
            const cell = row.querySelectorAll('td')[col];
            return cell ? cell.innerText.trim() : '';
        };

        function render() {
            allRows.forEach(r => r.style.display = 'none');
            const start = (currentPage - 1) * pageSize;
            const end = Math.min(start + pageSize, filtered.length);

            let noRes = document.getElementById('ct-no-results-row');
            if (filtered.length === 0) {
                if (!noRes) {
                    noRes = document.createElement('tr');
                    noRes.id = 'ct-no-results-row';
                    noRes.innerHTML = '<td colspan="' + colCount + '" class="ct-no-results">No matching records found</td>';
                    tbody.appendChild(noRes);
                }
                noRes.style.display = '';
            } else {
                if (noRes) { noRes.style.display = 'none'; }
                filtered.slice(start, end).forEach(r => r.style.display = '');
            }

            infoEl.textContent = filtered.length === 0
                ? 'No entries found'
                : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + filtered.length + ' entries'
                  + (filtered.length < totalRows ? ' (filtered from ' + totalRows + ' total)' : '');

            renderPagination();
        }

        function renderPagination() {
            const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
            pagEl.innerHTML = '';
            const btn = (label, page, disabled, active) => {
                const b = document.createElement('button');
                b.innerHTML = label;
                b.disabled = !!disabled;
                if (active) { b.classList.add('active'); }
                b.onclick = () => { currentPage = page; render(); };
                return b;
            };
            pagEl.appendChild(btn('‹', currentPage - 1, currentPage === 1, false));
            const delta = 2; let prev = null;
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                    if (prev !== null && i - prev > 1) {
                        const dots = document.createElement('button');
                        dots.textContent = '…'; dots.disabled = true; pagEl.appendChild(dots);
                    }
                    pagEl.appendChild(btn(i, i, false, i === currentPage));
                    prev = i;
                }
            }
            pagEl.appendChild(btn('›', currentPage + 1, currentPage === totalPages, false));
        }

        const searchInput = document.getElementById('ctSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const q = this.value.toLowerCase();
                filtered = allRows.filter(row =>
                    Array.from(row.querySelectorAll('td')).some(td => td.innerText.toLowerCase().includes(q))
                );
                currentPage = 1;
                render();
            });
        }

        const pageSizeSelect = document.getElementById('ctPageSize');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function () {
                pageSize = this.value === 'all' ? Number.MAX_SAFE_INTEGER : parseInt(this.value, 10);
                currentPage = 1;
                render();
            });
        }

        // Column sorting (all columns except the Action column, index 0)
        headers.forEach(function (th, idx) {
            if (idx === 0) { return; }
            th.classList.add('sortable');
            th.addEventListener('click', function () {
                sortDir = (sortCol === idx) ? -sortDir : 1;
                sortCol = idx;
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
                filtered.sort(function (a, b) {
                    const av = cellText(a, idx).replace(/[৳,]/g, '');
                    const bv = cellText(b, idx).replace(/[৳,]/g, '');
                    const an = parseFloat(av), bn = parseFloat(bv);
                    if (!isNaN(an) && !isNaN(bn)) { return (an - bn) * sortDir; }
                    return av.localeCompare(bv) * sortDir;
                });
                filtered.forEach(r => tbody.appendChild(r));
                currentPage = 1;
                render();
            });
        });

        // ----- Exports (skip the Action column and any column hidden via Column visibility) -----
        const exportCols = () => headers
            .map((th, i) => i)
            .filter(i => i !== 0 && !headers[i].classList.contains('ct-hidden'));

        function matrix() {
            const cols = exportCols();
            const head = cols.map(i => headers[i].innerText.trim());
            const body = filtered.map(function (row) {
                const cells = row.querySelectorAll('td');
                return cols.map(i => cells[i] ? cells[i].innerText.trim().replace(/\s+/g, ' ') : '');
            });
            return { head, body };
        }

        const filename = @json(strtolower($pageTitle));

        const csvBtn = document.getElementById('ctExportCsv');
        if (csvBtn) csvBtn.addEventListener('click', function () {
            const m = matrix();
            const csv = [m.head].concat(m.body)
                .map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(','))
                .join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            a.download = filename + '.csv';
            a.click();
        });

        const excelBtn = document.getElementById('ctExportExcel');
        if (excelBtn) excelBtn.addEventListener('click', function () {
            const m = matrix();
            let html = '<table border="1"><thead><tr>' + m.head.map(h => '<th>' + h + '</th>').join('') + '</tr></thead><tbody>';
            html += m.body.map(r => '<tr>' + r.map(c => '<td>' + c + '</td>').join('') + '</tr>').join('');
            html += '</tbody></table>';
            const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename + '.xls';
            a.click();
        });

        const printBtn = document.getElementById('ctPrint');
        if (printBtn) printBtn.addEventListener('click', () => window.print());
        const pdfBtn = document.getElementById('ctExportPdf');
        if (pdfBtn) pdfBtn.addEventListener('click', () => window.print());

        // ----- Column visibility menu -----
        const colvisMenu = document.getElementById('ctColvisMenu');
        if (colvisMenu) {
            colvisMenu.addEventListener('click', e => e.stopPropagation()); // keep menu open while toggling
            headers.forEach(function (th, idx) {
                if (idx === 0) { return; }
                const li = document.createElement('li');
                li.innerHTML = '<label class="dropdown-item mb-0" style="cursor:pointer;"><input type="checkbox" checked class="form-check-input me-2"> ' + th.innerText.trim() + '</label>';
                li.querySelector('input').addEventListener('change', function () {
                    const show = this.checked;
                    th.classList.toggle('ct-hidden', !show);
                    th.style.display = show ? '' : 'none';
                    allRows.forEach(function (row) {
                        const cell = row.querySelectorAll('td')[idx];
                        if (cell) { cell.style.display = show ? '' : 'none'; }
                    });
                });
                colvisMenu.appendChild(li);
            });
        }

        render();
    })();

});
</script>

@endsection
