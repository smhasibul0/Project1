@props([
    'accounts',
    'selected' => null,
])
{{-- Payment account <option> list. Balances render for admins only (gate
     'accounts.view-balance'), so other roles can pay from an account without
     seeing what it holds. An account holding dollars carries what they cost on
     average, so a form paying in dollars can show the taka they come to. --}}
@foreach($accounts as $account)
@php $usdRate = $account->averageUsdRate(); @endphp
<option value="{{ $account->id }}" @selected($selected !== null && (int) $selected === (int) $account->id)
    @if($usdRate) data-usd-rate="{{ $usdRate }}" @can('accounts.view-balance') data-usd-balance="{{ (float) $account->usd_balance }}" @endcan @endif
>{{ $account->name }}@can('accounts.view-balance') (৳ {{ number_format($account->balance, 2) }}{{ (float) $account->usd_balance > 0 ? ' + $'.number_format($account->usd_balance, 2) : '' }})@endcan</option>
@endforeach
