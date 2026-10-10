{{-- Pay in taka or dollars, beside a form's amount and account fields.

     direction="out": the dollars come out of the chosen account, costed at what its
     dollars cost on average — the taka amount fills itself.
     direction="in": dollars come into the account at the rate given (the day's rate to
     start with) — the taka amount is dollars × rate.
     In taka the form works as it always has. --}}
@props([
    'direction' => 'out',
    'amountField' => 'amount',
    'accountField' => 'payment_account_id',
    'rate' => null,
])
@php
    $uid = 'cur_'.Str::random(6);
    // Money coming in starts at the day's rate; paying out is costed from the account.
    $startRate = $direction === 'in' ? ($rate ?? \App\Models\ExchangeRate::forDate()?->usd_rate) : null;
    $inDollars = old('currency') === 'USD';
@endphp
<div {{ $attributes->merge(['class' => 'col-12 currency-choice']) }} data-direction="{{ $direction }}" data-amount-field="{{ $amountField }}" data-account-field="{{ $accountField }}">
    <div class="d-flex flex-wrap align-items-center gap-2 border rounded px-3 py-2 bg-light">
        <span class="small text-muted me-1">{{ $direction === 'in' ? 'Received in' : 'Paid in' }}</span>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check currency-radio" name="currency" id="{{ $uid }}_bdt" value="BDT" @checked(! $inDollars)>
            <label class="btn btn-outline-primary" for="{{ $uid }}_bdt">৳ Taka</label>
            <input type="radio" class="btn-check currency-radio" name="currency" id="{{ $uid }}_usd" value="USD" @checked($inDollars)>
            <label class="btn btn-outline-primary" for="{{ $uid }}_usd">$ Dollars</label>
        </div>
        <div class="currency-usd d-flex flex-wrap align-items-center gap-2 {{ $inDollars ? '' : 'd-none' }}">
            <div class="input-group input-group-sm" style="width:160px">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0.01" class="form-control" name="usd_amount" value="{{ old('usd_amount') }}" placeholder="Dollars" aria-label="Dollars">
            </div>
            @if($direction === 'in')
            <div class="input-group input-group-sm" style="width:180px">
                <span class="input-group-text">৳ per $</span>
                <input type="number" step="0.0001" min="0.0001" class="form-control" name="usd_rate" value="{{ old('usd_rate', $startRate !== null ? (float) $startRate : null) }}" aria-label="Rate">
            </div>
            @endif
        </div>
        <small class="currency-preview text-muted"></small>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    const grouped = n => (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const plain = n => (Math.round((Number(n) || 0) * 10000) / 10000).toLocaleString('en-US', { maximumFractionDigits: 4 });
    const round2 = n => Math.round(Number((Number(n) || 0).toPrecision(12)) * 100) / 100;

    document.querySelectorAll('.currency-choice').forEach(function (box) {
        const form = box.closest('form');
        if (!form) { return; }
        const amount = form.querySelector('[name="' + box.dataset.amountField + '"]');
        const account = form.querySelector('[name="' + box.dataset.accountField + '"]');
        const usd = box.querySelector('[name="usd_amount"]');
        const rate = box.querySelector('[name="usd_rate"]');
        const preview = box.querySelector('.currency-preview');
        const paysOut = box.dataset.direction === 'out';
        const isDollar = () => box.querySelector('.currency-radio[value="USD"]').checked;

        [amount, account].forEach(el => { if (el) { el.dataset.wasRequired = el.required ? '1' : ''; } });

        function update() {
            const dollar = isDollar();
            box.querySelector('.currency-usd').classList.toggle('d-none', !dollar);
            usd.required = dollar;
            if (rate) { rate.required = dollar; }
            if (amount) {
                amount.readOnly = dollar;
                amount.required = dollar ? false : amount.dataset.wasRequired === '1';
            }
            if (account) {
                // Dollars come out of, or go into, an account.
                account.required = dollar || account.dataset.wasRequired === '1';
                if (paysOut) {
                    [...account.options].forEach(o => { if (o.value) { o.disabled = dollar && !o.dataset.usdRate; } });
                    if (dollar && account.selectedOptions[0]?.disabled) { account.value = ''; }
                }
            }
            if (!dollar) { preview.textContent = ''; return; }

            const dollars = parseFloat(usd.value) || 0;
            if (paysOut) {
                const option = account ? account.selectedOptions[0] : null;
                const average = parseFloat(option?.dataset.usdRate) || 0;
                if (!option || !option.value) { preview.textContent = 'Choose the account the dollars come from.'; return; }
                const taka = round2(dollars * average);
                if (amount) { amount.value = dollars ? taka.toFixed(2) : ''; }
                preview.textContent = (dollars ? '= ৳' + grouped(taka) + ' — ' : '') + 'its dollars cost ৳' + plain(average) + ' each' +
                    (option.dataset.usdBalance ? ' (holds $' + grouped(option.dataset.usdBalance) + ')' : '');
            } else {
                const perDollar = parseFloat(rate?.value) || 0;
                const taka = round2(dollars * perDollar);
                if (amount) { amount.value = dollars && perDollar ? taka.toFixed(2) : ''; }
                preview.textContent = dollars && perDollar ? '= ৳' + grouped(taka) + ' — the dollars are kept in the account' : 'The dollars are kept in the account.';
            }
        }

        box.querySelectorAll('.currency-radio').forEach(el => el.addEventListener('change', update));
        [usd, rate, account].forEach(el => { if (el) { el.addEventListener('input', update); el.addEventListener('change', update); } });
        update();
    });
});
</script>
@endonce
