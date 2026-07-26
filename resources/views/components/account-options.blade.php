@props([
    'accounts',
    'selected' => null,
])
{{-- Payment account <option> list. Balances render for admins only (gate
     'accounts.view-balance'), so other roles can pay from an account without
     seeing what it holds. --}}
@foreach($accounts as $account)
<option value="{{ $account->id }}" @selected($selected !== null && (int) $selected === (int) $account->id)>{{ $account->name }}@can('accounts.view-balance') (৳ {{ number_format($account->balance, 2) }})@endcan</option>
@endforeach
