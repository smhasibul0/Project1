{{-- Shared loan fields for the add and edit forms.
     Expects $interestTypes, $counterpartyTypes, $accounts, $partyLabel,
     $partyHint, $accountHint, a unique $prefix, and $loan (null when adding). --}}
@php $loan = $loan ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ $partyLabel }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="counterparty" value="{{ $loan?->counterparty }}" required placeholder="e.g. City Bank or Mr Rahman">
        <small class="text-muted">{{ $partyHint }}</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">They are a <span class="text-danger">*</span></label>
        <select class="form-control" name="counterparty_type" required>
            @foreach($counterpartyTypes as $key => $label)
            <option value="{{ $key }}" @selected(($loan?->counterparty_type ?? 'bank') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Principal <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" class="form-control" name="principal" value="{{ $loan?->principal }}" required>
        @if($loan && $loan->paidTotal() > 0)
        <small class="text-muted">Cannot go below ৳ {{ number_format($loan->paidTotal(), 2) }} already settled.</small>
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label">Start date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="start_date" value="{{ $loan?->start_date?->toDateString() ?? now()->toDateString() }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Due date</label>
        <input type="date" class="form-control" name="due_date" value="{{ $loan?->due_date?->toDateString() }}">
        <small class="text-muted">Leave empty for an open-ended arrangement.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Interest <span class="text-danger">*</span></label>
        <select class="form-control interest-type" name="interest_type" data-prefix="{{ $prefix }}" required>
            @foreach($interestTypes as $key => $label)
            <option value="{{ $key }}" @selected(($loan?->interest_type ?? 'none') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4" id="{{ $prefix }}RateWrap">
        <label class="form-label">Rate (% a year) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" max="100" class="form-control" name="interest_rate" value="{{ $loan?->interest_rate }}">
        <small class="text-muted">Simple interest on the principal, for the days the money is out.</small>
    </div>
    <div class="col-md-4" id="{{ $prefix }}AmountWrap">
        <label class="form-label">Interest amount <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" class="form-control" name="interest_amount" value="{{ $loan?->interest_amount }}">
        <small class="text-muted">One flat charge for the whole loan, owed from day one.</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Payment account</label>
        @if($loan)
        <input type="text" class="form-control" value="{{ $loan->account->name ?? 'None' }}" disabled>
        <small class="text-muted">Set when the loan was recorded; changing it would mean unwinding a posted ledger entry.</small>
        @else
        <select class="form-control" name="payment_account_id">
            <option value="">-- None --</option>
            <x-account-options :accounts="$accounts" />
        </select>
        <small class="text-muted">{{ $accountHint }}</small>
        @endif
    </div>
    @unless($loan)
    {{-- Borrowed dollars come into the account; lent ones go out of its dollars. --}}
    <x-currency-choice :direction="$isBorrowed ? 'in' : 'out'" amount-field="principal" />
    @endunless
    <div class="col-md-6">
        <label class="form-label">Attachment</label>
        <input type="file" class="form-control" name="attachment">
        @if($loan?->attachment)
        <small class="text-muted"><a href="{{ asset('upload/loans/'.$loan->attachment) }}" target="_blank">Current file</a> — uploading replaces it.</small>
        @else
        <small class="text-muted">The agreement or sanction letter, if there is one.</small>
        @endif
    </div>
    <div class="col-12">
        <label class="form-label">Note</label>
        <textarea class="form-control" name="note" rows="1">{{ $loan?->note }}</textarea>
    </div>
</div>
