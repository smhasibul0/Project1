@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Compact row-action buttons for the accounts table */
    .acct-actions { max-width: 220px; }
    .acct-actions .btn { font-size: .68rem; padding: .12rem .45rem; line-height: 1.35; }
    .acct-actions .btn i { font-size: .8rem; vertical-align: -1px; }
</style>

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Payment Accounts</h4>
                <small class="text-muted">Manage your account</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Payment Accounts</li>
                </ol>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-3" id="accountTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center gap-1" id="accounts-tab"
                    data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab">
                    <i class="ri-bank-card-line"></i> Accounts
                </button>
            </li>
            @can('account-types.view')
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center gap-1" id="account-types-tab"
                    data-bs-toggle="tab" data-bs-target="#account-types" type="button" role="tab">
                    <i class="ri-list-unordered"></i> Account Types
                </button>
            </li>
            @endcan
        </ul>

        <div class="tab-content" id="accountTabsContent">

            {{-- ===================== ACCOUNTS TAB ===================== --}}
            <div class="tab-pane fade show active" id="accounts" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Payment Accounts</h5>
                        @can('accounts.create')
                        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="ri-add-line me-1"></i> Add
                        </button>
                        @endcan
                    </div>

                    <div class="card-body p-0">
                        <x-data-table id="accountsTable" export-name="payment-accounts">
                            <table class="ct-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Account Type</th>
                                        <th>Account Number</th>
                                        <th>Note</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-end">Dollars Sent</th>
                                        <th data-filter="Status">Status</th>
                                        <th>Account Details</th>
                                        <th>Added By</th>
                                        <th class="dt-noexport">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accounts as $account)
                                    <tr>
                                        <td>{{ $account->name }}</td>
                                        <td>{{ $account->accountType->name ?? '-' }}</td>
                                        <td>{{ $account->account_number ?? '-' }}</td>
                                        <td>{{ $account->note ?? '-' }}</td>
                                        <td class="text-end">৳ {{ number_format($account->balance, 2) }}</td>
                                        <td class="text-end">{{ $account->usd_sent > 0 ? '$'.number_format($account->usd_sent, 2) : '—' }}</td>
                                        <td><span class="badge bg-{{ $account->is_active ? 'success' : 'secondary' }}">{{ $account->is_active ? 'Active' : 'Closed' }}</span></td>
                                        <td>{{ $account->account_details ?? '-' }}</td>
                                        <td>{{ $account->addedBy->name ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1 acct-actions">
                                                @can('accounts.edit')
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary edit-account-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editAccountModal"
                                                        data-id="{{ $account->id }}"
                                                        data-name="{{ $account->name }}"
                                                        data-account-type-id="{{ $account->account_type_id }}"
                                                        data-account-number="{{ $account->account_number }}"
                                                        data-account-details="{{ $account->account_details }}"
                                                        data-note="{{ $account->note }}"
                                                        data-is-active="{{ $account->is_active ? 1 : 0 }}"
                                                        title="Edit">
                                                    <i class="ri-edit-line"></i> Edit
                                                </button>
                                                @endcan
                                                <a href="{{ route('payment.account.book', $account->id) }}"
                                                   class="btn btn-sm btn-outline-warning" title="Account Book">
                                                    <i class="ri-book-line"></i> Book
                                                </a>
                                                @can('accounts.fund-transfer')
                                                <button class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="modal" data-bs-target="#fundTransferModal"
                                                        data-account-id="{{ $account->id }}"
                                                        data-account-name="{{ $account->name }}" title="Fund Transfer">
                                                    <i class="ri-exchange-line"></i> Transfer
                                                </button>
                                                @endcan
                                                @can('accounts.deposit')
                                                <button class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="modal" data-bs-target="#depositModal"
                                                        data-account-id="{{ $account->id }}"
                                                        data-account-name="{{ $account->name }}" title="Deposit">
                                                    <i class="ri-money-dollar-circle-line"></i> Deposit
                                                </button>
                                                @endcan
                                                @can('accounts.toggle')
                                                <form action="{{ route('payment.account.toggle', $account->id) }}" method="POST" class="m-0">
                                                    @csrf @method('PATCH')
                                                    <button type="button"
                                                            class="btn btn-sm toggle-account-btn {{ $account->is_active ? 'btn-outline-danger' : 'btn-outline-secondary' }}"
                                                            data-action="{{ $account->is_active ? 'deactivate' : 'activate' }}"
                                                            title="{{ $account->is_active ? 'Deactivate' : 'Activate' }}">
                                                        <i class="ri-{{ $account->is_active ? 'forbid-line' : 'check-line' }}"></i> {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No accounts found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </x-data-table>
                    </div>
                </div>
            </div>

            {{-- ===================== ACCOUNT TYPES TAB ===================== --}}
            @can('account-types.view')
            <div class="tab-pane fade" id="account-types" role="tabpanel">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Account Types</h5>

                    @can('account-types.create')
                    <button class="btn btn-primary rounded-pill px-4"
                        data-bs-toggle="modal"
                        data-bs-target="#addAccountTypeModal">
                        <i class="ri-add-line me-1"></i> Add
                    </button>
                    @endcan
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">

                        <table class="table table-bordered table-hover mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th width="200">Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($accountTypes as $type)

                                <tr>
                                    <td>{{ $type->name }}</td>

                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            @can('account-types.edit')
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 edit-account-type-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editAccountTypeModal"
                                                    data-id="{{ $type->id }}"
                                                    data-name="{{ $type->name }}"
                                                    data-description="{{ $type->description }}">
                                                <i class="ri-edit-line"></i> Edit
                                            </button>
                                            @endcan

                                            @can('account-types.delete')
                                            <form action="{{ route('account.type.delete', $type->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 delete-account-type-btn">
                                                    <i class="ri-delete-bin-line"></i> Delete
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>

                                @empty

                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">
                                        No account types found
                                    </td>
                                </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>
                </div>

            </div>
        </div>
        @endcan

        </div>{{-- end tab-content --}}
    </div>
</div>

{{-- ===================== ADD ACCOUNT MODAL ===================== --}}
@can('accounts.create')
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addAccountForm" action="{{ route('payment.account.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccountModalLabel">Add Payment Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    <div class="form-group col-md-6">
                        <label class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Cash in Hand">
                    </div>

                    <div class="form-group col-md-6">
                        <label class="form-label">Account Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="account_type_id" id="modalAccountType">
                            <option value="">-- Select Account Type --</option>
                            @foreach($accountTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" placeholder="e.g. 01875133644">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Opening Balance</label>
                        <input type="number" step="0.01" class="form-control" name="opening_balance" value="0.00" min="0">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Account Details</label>
                        <input type="text" class="form-control" name="account_details" placeholder="Optional details">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="2" placeholder="Optional note"></textarea>
                    </div>

                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="accountActiveToggle" value="1" checked>
                            <label class="form-check-label fw-semibold" for="accountActiveToggle">Active</label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- ===================== EDIT ACCOUNT MODAL ===================== --}}
@can('accounts.edit')
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editAccountForm" action="{{ url('payment/account') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id" id="editAccountId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccountModalLabel">Edit Payment Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    <div class="form-group col-md-6">
                        <label class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editAccountName" placeholder="e.g. Cash in Hand">
                    </div>

                    <div class="form-group col-md-6">
                        <label class="form-label">Account Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="account_type_id" id="editAccountType">
                            <option value="">-- Select Account Type --</option>
                            @foreach($accountTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" id="editAccountNumber" placeholder="e.g. 01875133644">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Account Details</label>
                        <input type="text" class="form-control" name="account_details" id="editAccountDetails" placeholder="Optional details">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" id="editAccountNote" rows="2" placeholder="Optional note"></textarea>
                    </div>

                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editAccountActiveToggle" value="1">
                            <label class="form-check-label fw-semibold" for="editAccountActiveToggle">Active</label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- ===================== FUND TRANSFER MODAL ===================== --}}
@can('accounts.fund-transfer')
<div class="modal fade" id="fundTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="fundTransferForm" action="{{ route('payment.account.fund.transfer') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold">Fund Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    {{-- Transfer From --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Transfer from: <span class="text-danger">*</span></label>
                        <select class="form-control" name="from_account_id" id="ftFromAccountId">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Transfer To --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Transfer To: <span class="text-danger">*</span></label>
                        <select class="form-control" name="to_account_id" id="ftToAccountId">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Amount --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Amount: <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="amount" value="0" min="0.01">
                    </div>

                    {{-- Date --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Date: <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="transfer_date" value="{{ date('Y-m-d') }}">
                    </div>

                    {{-- Note --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea class="form-control" name="note" rows="4" placeholder="Note"></textarea>
                    </div>

                    {{-- Attach Document --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Attach Document:</label>
                        <input type="file" class="form-control" name="document"
                               accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                        <small class="text-muted d-block mt-1">Max File size: 5MB</small>
                        <small class="text-muted">Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</small>
                    </div>

                </div>
                <div class="modal-footer border-top">
                    <button type="submit" class="btn btn-primary px-4">Submit</button>
                    <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- ===================== DEPOSIT MODAL ===================== --}}
@can('accounts.deposit')
<div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="depositForm" action="{{ route('payment.account.deposit') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold">Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    {{-- Deposit To: pre-selected to the clicked account, but changeable --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Deposit to: <span class="text-danger">*</span></label>
                        <select class="form-control" name="account_id" id="depositToSelect">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Amount --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Amount: <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="amount" value="0" min="0.01">
                    </div>

                    {{-- Deposit From --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Deposit From:</label>
                        <select class="form-control" name="deposit_from">
                            <option value="">Please Select</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="cheque">Cheque</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {{-- Date --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Date: <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="deposit_date" value="{{ date('Y-m-d') }}">
                    </div>

                    {{-- Note --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea class="form-control" name="note" rows="4" placeholder="Note"></textarea>
                    </div>

                </div>
                <div class="modal-footer border-top">
                    <button type="submit" class="btn btn-primary px-4">Submit</button>
                    <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- ===================== ADD ACCOUNT TYPE MODAL ===================== --}}
@can('account-types.create')
<div class="modal fade" id="addAccountTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addAccountTypeForm" action="{{ route('account.type.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Account Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="form-group col-12">
                        <label class="form-label">Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Cash in Hand, Bank, bKash Merchant">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
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

{{-- ===================== EDIT ACCOUNT TYPE MODAL ===================== --}}
@can('account-types.edit')
<div class="modal fade" id="editAccountTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAccountTypeForm" action="{{ url('account-type') }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id" id="editAccountTypeId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Account Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="form-group col-12">
                        <label class="form-label">Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editAccountTypeName" placeholder="e.g. Cash in Hand, Bank, bKash Merchant">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editAccountTypeDescription" rows="2"></textarea>
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
$(document).ready(function () {

    // Fallback shim: if the jQuery Validation plugin fails to load, replace .validate()
    // with a harmless no-op so a missing script can never abort the modal handlers below
    // (server-side validation still protects every request).
    if (typeof $.fn.validate !== 'function') {
        $.fn.validate = function () {
            return { resetForm: function () {} };
        };
    }

    // ----- Edit Account modal: pre-fill fields when opened via row button -----
    $(document).on('click', '.edit-account-btn', function () {
        const btn = $(this);
        const id = btn.data('id');

        $('#editAccountId').val(id);
        $('#editAccountName').val(btn.data('name'));
        $('#editAccountType').val(btn.data('account-type-id'));
        $('#editAccountNumber').val(btn.data('account-number'));
        $('#editAccountDetails').val(btn.data('account-details'));
        $('#editAccountNote').val(btn.data('note'));
        $('#editAccountActiveToggle').prop('checked', btn.data('is-active') == 1);

        // Point the form at /payment/account/{id}
        $('#editAccountForm').attr('action', '{{ url('payment/account') }}/' + id);

        // Clear any stale validation state from a previous open
        $('#editAccountForm').validate().resetForm();
        $('#editAccountForm .is-invalid').removeClass('is-invalid');
    });

    // ----- Edit Account form validation -----
    $('#editAccountForm').validate({
        rules: {
            name:            { required: true },
            account_type_id: { required: true },
        },
        messages: {
            name:            { required: 'Please enter account name' },
            account_type_id: { required: 'Please select account type' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group, .col-md-6, .col-md-12').first().append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Fund Transfer modal: pre-select "from" account when opened via row button -----
    $('#fundTransferModal').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        const accountId = btn.data('account-id');
        if (accountId) {
            $('#ftFromAccountId').val(accountId);
        }
    });

    // ----- Fund Transfer form validation -----
    $('#fundTransferForm').validate({
        rules: {
            from_account_id: { required: true },
            to_account_id:   { required: true },
            amount:          { required: true, min: 0.01 },
            transfer_date:   { required: true },
        },
        messages: {
            from_account_id: { required: 'Please select the source account' },
            to_account_id:   { required: 'Please select the destination account' },
            amount:          { required: 'Please enter an amount', min: 'Amount must be greater than 0' },
            transfer_date:   { required: 'Please select a date' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Deposit modal: pre-fill account id, name, and "Deposit to" dropdown -----
    $('#depositModal').on('show.bs.modal', function (e) {
        const btn       = $(e.relatedTarget);
        const accountId = btn.data('account-id');
        // Pre-select the account whose Deposit button was clicked; the user can change it.
        $('#depositToSelect').val(accountId);
    });

    // ----- Deposit form validation -----
    $('#depositForm').validate({
        rules: {
            account_id:   { required: true },
            amount:       { required: true, min: 0.01 },
            deposit_date: { required: true },
        },
        messages: {
            account_id:   { required: 'Please select an account' },
            amount:       { required: 'Please enter an amount', min: 'Amount must be greater than 0' },
            deposit_date: { required: 'Please select a date' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Activate / Deactivate account: SweetAlert2 confirmation -----
    $(document).on('click', '.toggle-account-btn', function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        const action = $(this).data('action') || 'update'; // 'activate' | 'deactivate'
        Swal.fire({
            title: action.charAt(0).toUpperCase() + action.slice(1) + ' this account?',
            icon: 'warning',
            iconColor: '#f59e0b',
            showCancelButton: true,
            confirmButtonText: 'Yes, ' + action,
            cancelButtonText: 'Cancel',
            confirmButtonColor: action === 'activate' ? '#16a34a' : '#ef4444',
            cancelButtonColor: '#e5e7eb',
            customClass: {
                cancelButton: 'text-dark',
            },
            reverseButtons: false,
        }).then(result => {
            if (result.isConfirmed) form.submit();
        });
    });

    // ----- Add Account form validation -----
    $('#addAccountForm').validate({
        rules: {
            name:            { required: true },
            account_type_id: { required: true },
        },
        messages: {
            name:            { required: 'Please enter account name' },
            account_type_id: { required: 'Please select account type' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Edit Account Type modal: pre-fill fields when opened via row button -----
    $(document).on('click', '.edit-account-type-btn', function () {
        const btn = $(this);
        const id = btn.data('id');

        $('#editAccountTypeId').val(id);
        $('#editAccountTypeName').val(btn.data('name'));
        $('#editAccountTypeDescription').val(btn.data('description'));

        // Point the form at /account-type/{id}
        $('#editAccountTypeForm').attr('action', '{{ url('account-type') }}/' + id);

        // Clear any stale validation state from a previous open
        $('#editAccountTypeForm').validate().resetForm();
        $('#editAccountTypeForm .is-invalid').removeClass('is-invalid');
    });

    // ----- Add Account Type form validation -----
    $('#addAccountTypeForm').validate({
        rules: {
            name: { required: true },
        },
        messages: {
            name: { required: 'Please enter type name' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group, .col-12').first().append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Edit Account Type form validation -----
    $('#editAccountTypeForm').validate({
        rules: {
            name: { required: true },
        },
        messages: {
            name: { required: 'Please enter type name' },
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group, .col-12').first().append(error);
        },
        highlight:   function (el) { $(el).addClass('is-invalid'); },
        unhighlight: function (el) { $(el).removeClass('is-invalid'); },
    });

    // ----- Delete Account Type: SweetAlert2 confirmation -----
    $(document).on('click', '.delete-account-type-btn', function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure ?',
            text: 'This will permanently delete this account type.',
            icon: 'warning',
            iconColor: '#f59e0b',
            showCancelButton: true,
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#e5e7eb',
            customClass: {
                cancelButton: 'text-dark',
            },
            reverseButtons: false,
        }).then(result => {
            if (result.isConfirmed) form.submit();
        });
    });

});
</script>

@endsection