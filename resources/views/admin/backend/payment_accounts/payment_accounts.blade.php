@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center gap-1" id="account-types-tab"
                    data-bs-toggle="tab" data-bs-target="#account-types" type="button" role="tab">
                    <i class="ri-list-unordered"></i> Account Types
                </button>
            </li>
        </ul>

        <div class="tab-content" id="accountTabsContent">

            {{-- ===================== ACCOUNTS TAB ===================== --}}
            <div class="tab-pane fade show active" id="accounts" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0 me-1">Status:</label>
                            <select class="form-select form-select-sm" id="statusFilter" style="width:160px;">
                                <option value="active" selected>Active</option>
                                <option value="closed">Closed</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="ri-add-line me-1"></i> Add
                        </button>
                    </div>

                    <div class="card-body">
                        {{-- DataTable toolbar --}}
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                            <label class="mb-0">Show</label>
                            <select class="form-select form-select-sm" style="width:80px;" id="showEntries">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100" selected>100</option>
                            </select>
                            <label class="mb-0">entries</label>

                            <button class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="ri-file-text-line me-1"></i>Export CSV
                            </button>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="ri-file-excel-line me-1"></i>Export Excel
                            </button>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="ri-printer-line me-1"></i>Print
                            </button>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="ri-eye-line me-1"></i>Column visibility
                            </button>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="ri-file-pdf-line me-1"></i>Export PDF
                            </button>

                            <div class="ms-auto">
                                <input type="text" class="form-control form-control-sm" placeholder="Search ..." id="tableSearch" style="width:200px;">
                            </div>
                        </div>

                        {{-- Table --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="accountsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Name <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Account Type <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Account Number <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Note <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Balance <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Account details</th>
                                        <th>Added By <i class="ri-arrow-up-down-line text-muted"></i></th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accounts as $index => $account)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $account->name }}</td>
                                        <td>{{ $account->accountType->name ?? '-' }}</td>
                                        <td>{{ $account->account_number ?? '-' }}</td>
                                        <td>{{ $account->note ?? '-' }}</td>
                                        <td>৳ {{ number_format($account->balance, 2) }}</td>
                                        <td>{{ $account->account_details ?? '-' }}</td>
                                        <td>{{ $account->addedBy->name ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="{{ route('payment.account.edit', $account->id) }}"
                                                   class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                                    <i class="ri-edit-line"></i> Edit
                                                </a>
                                                <a href="{{ route('payment.account.book', $account->id) }}"
                                                   class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1">
                                                    <i class="ri-book-line"></i> Account Book
                                                </a>
                                                <button class="btn btn-sm btn-outline-info d-flex align-items-center gap-1"
                                                        data-bs-toggle="modal" data-bs-target="#fundTransferModal"
                                                        data-account-id="{{ $account->id }}"
                                                        data-account-name="{{ $account->name }}">
                                                    <i class="ri-exchange-line"></i> Fund Transfer
                                                </button>
                                                <button class="btn btn-sm btn-outline-success d-flex align-items-center gap-1"
                                                        data-bs-toggle="modal" data-bs-target="#depositModal"
                                                        data-account-id="{{ $account->id }}"
                                                        data-account-name="{{ $account->name }}">
                                                    <i class="ri-money-dollar-circle-line"></i> Deposit
                                                </button>
                                                @if($account->is_active)
                                                <form action="{{ route('payment.account.close', $account->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 w-100 close-account-btn">
                                                        <i class="ri-power-off-line"></i> Close
                                                    </button>
                                                </form>
                                                @endif
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
                        </div>

                        {{-- Pagination --}}
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                Showing {{ $accounts->firstItem() ?? 0 }} to {{ $accounts->lastItem() ?? 0 }}
                                of {{ $accounts->total() ?? 0 }} entries
                            </small>
                            {{ $accounts->links() }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== ACCOUNT TYPES TAB ===================== --}}
            <div class="tab-pane fade" id="account-types" role="tabpanel">
            <div class="card">
                
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Account Types</h5>

                    <button class="btn btn-primary btn-sm px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#addAccountTypeModal">
                        <i class="ri-add-line"></i> Add
                    </button>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">

                        <table class="table table-bordered table-hover mb-0">
                            
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th width="180">Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($accountTypes as $type)

                                <tr>
                                    <td>{{ $type->name }}</td>

                                    <td>
                                        <a href="{{ route('account.type.edit', $type->id) }}" 
                                        class="btn btn-sm btn-success">
                                        Edit
                                        </a>

                                        <a href="{{ route('account.type.delete', $type->id) }}" 
                                        class="btn btn-sm btn-danger" 
                                        id="delete">
                                        Delete
                                        </a>
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

        </div>{{-- end tab-content --}}
    </div>
</div>

{{-- ===================== ADD ACCOUNT MODAL ===================== --}}
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

{{-- ===================== FUND TRANSFER MODAL ===================== --}}
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

{{-- ===================== DEPOSIT MODAL ===================== --}}
<div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="depositForm" action="{{ route('payment.account.deposit') }}" method="POST">
                @csrf
                <input type="hidden" name="account_id" id="depositAccountId">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold">Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    {{-- Selected Account (readonly info line) --}}
                    <div class="col-12">
                        <p class="mb-0">
                            <strong>Selected Account:</strong>
                            <span id="depositAccountName" class="ms-1 text-muted">—</span>
                        </p>
                    </div>

                    {{-- Deposit To (pre-selected, editable) --}}
                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Deposit to:</label>
                        <select class="form-control" name="account_id_to" id="depositToSelect">
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

{{-- ===================== ADD ACCOUNT TYPE MODAL ===================== --}}
<div class="modal fade" id="addAccountTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('account.type.store') }}" method="POST">
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

<script>
$(document).ready(function () {

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
        const accountName = btn.data('account-name');
        $('#depositAccountId').val(accountId);
        $('#depositAccountName').text(accountName);
        $('#depositToSelect').val(accountId);
    });

    // ----- Deposit form validation -----
    $('#depositForm').validate({
        rules: {
            amount:       { required: true, min: 0.01 },
            deposit_date: { required: true },
        },
        messages: {
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

    // ----- Close Account: SweetAlert2 confirmation -----
    $(document).on('click', '.close-account-btn', function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure ?',
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

    // ----- Simple client-side search -----
    $('#tableSearch').on('keyup', function () {
        const val = $(this).val().toLowerCase();
        $('#accountsTable tbody tr').filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
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

});
</script>

@endsection