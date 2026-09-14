@props(['accounts'])

{{-- Edit/delete dialog for manually created ledger entries (deposits & fund transfers).
     Pair it with rows that carry an `.edit-txn-btn` (data-* attributes below) and a delete
     form whose submit button has class `.delete-txn-btn`. Used by the Account Book and the
     Cash Flow report. --}}
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTransactionForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-semibold">Edit Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">

                    <div class="col-12">
                        <p class="mb-0"><strong>Transaction:</strong>
                            <span id="etDescription" class="ms-1 text-muted">—</span>
                        </p>
                    </div>

                    {{-- Source/destination accounts: fund transfers only --}}
                    <div class="form-group col-12 et-transfer-field" id="etFromWrap">
                        <label class="form-label fw-semibold">Transfer from: <span class="text-danger">*</span></label>
                        <select class="form-control" name="from_account_id" id="etFromAccount">
                            <option value="">-- Select Account --</option>
                            <x-account-options :accounts="$accounts" />
                        </select>
                    </div>

                    <div class="form-group col-12 et-transfer-field" id="etToWrap">
                        <label class="form-label fw-semibold">Transfer to: <span class="text-danger">*</span></label>
                        <select class="form-control" name="to_account_id" id="etToAccount">
                            <option value="">-- Select Account --</option>
                            <x-account-options :accounts="$accounts" />
                        </select>
                    </div>

                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Amount: <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="etAmount">
                    </div>

                    <div class="form-group col-12">
                        <label class="form-label fw-semibold">Date: <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" id="etDate">
                    </div>

                    <div class="form-group col-12" id="etPaymentMethodWrap">
                        <label class="form-label fw-semibold">Deposit From:</label>
                        <select class="form-control" name="payment_method" id="etPaymentMethod">
                            <option value="">Please Select</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="cheque">Cheque</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea class="form-control" name="note" id="etNote" rows="3"></textarea>
                    </div>

                    <div class="col-12" id="etTransferHint" style="display:none;">
                        <small class="text-muted">
                            <i class="ri-information-line"></i>
                            This is a fund transfer — changes update both the source and destination accounts.
                        </small>
                    </div>

                </div>
                <div class="modal-footer border-top">
                    <button type="submit" class="btn btn-primary px-4">Update</button>
                    <button type="button" class="btn btn-dark px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ----- Edit Transaction modal: pre-fill from the clicked row -----
    const editModal = document.getElementById('editTransactionModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            const d   = btn.dataset;

            document.getElementById('editTransactionForm').action = '{{ url('transactions') }}/' + d.id;
            document.getElementById('etDescription').textContent   = d.description || '—';
            document.getElementById('etAmount').value              = d.amount;
            document.getElementById('etDate').value                = d.date;
            document.getElementById('etNote').value                = d.note || '';
            document.getElementById('etPaymentMethod').value       = d.paymentMethod || '';
            document.getElementById('etFromAccount').value         = d.fromAccountId || '';
            document.getElementById('etToAccount').value           = d.toAccountId || '';

            const isDeposit = d.source === 'deposit';

            // Payment method applies to deposits only; from/to accounts to transfers only.
            document.getElementById('etPaymentMethodWrap').style.display = isDeposit ? '' : 'none';
            document.getElementById('etTransferHint').style.display      = isDeposit ? 'none' : '';
            document.querySelectorAll('.et-transfer-field').forEach(function (el) {
                el.style.display = isDeposit ? 'none' : '';
            });
        });
    }

    // ----- Delete Transaction: confirm then submit -----
    document.querySelectorAll('.delete-txn-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = btn.closest('form');
            Swal.fire({
                title: 'Delete this transaction?',
                text: 'The account balance will be adjusted accordingly.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ef4444',
            }).then(function (result) {
                if (result.isConfirmed) { form.submit(); }
            });
        });
    });

});
</script>
