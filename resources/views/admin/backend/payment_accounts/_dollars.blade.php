{{-- The dollars this account holds: what they cost, where they came from and went,
     and paying dollars in, selling them for taka or moving them to another account. --}}
@php
    $avgRate = $account->averageUsdRate();
    $defaultRate = (float) ($dayRate?->usd_rate ?? 0) ?: '';
    $usd = fn ($value) => '$'.number_format((float) $value, 2);
@endphp

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="mb-0">Dollars <small class="text-muted fw-normal fs-6">— bought with LC payments or paid in; spent on costs, moved, or sold for taka</small></h5>
        <div class="d-flex flex-wrap gap-2">
            @can('accounts.deposit')
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#dollarDepositModal"><i class="ri-add-line me-1"></i> Deposit Dollars</button>
            @endcan
            @if((float) $account->usd_balance > 0)
            @can('accounts.sell-dollars')
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#dollarSellModal"><i class="ri-exchange-dollar-line me-1"></i> Sell for Taka</button>
            @endcan
            @can('accounts.fund-transfer')
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dollarTransferModal"><i class="ri-arrow-left-right-line me-1"></i> Transfer Dollars</button>
            @endcan
            @endif
        </div>
    </div>
    <div class="card-body border-bottom row g-3 text-center">
        <div class="col-4"><small class="text-muted d-block">Held</small><strong class="fs-5">{{ $usd($account->usd_balance) }}</strong></div>
        <div class="col-4"><small class="text-muted d-block">Average cost</small><strong class="fs-5">{{ $avgRate ? '৳ '.\App\Models\DollarTransaction::rate($avgRate) : '—' }}</strong><small class="d-block text-muted">per dollar</small></div>
        <div class="col-4"><small class="text-muted d-block">Cost in taka</small><strong class="fs-5">৳ {{ number_format($account->usd_cost, 2) }}</strong></div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle" style="font-size:.84rem">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th><th>Entry</th><th class="text-end">In</th><th class="text-end">Out</th>
                        <th class="text-end">Rate</th><th class="text-end">Taka at cost</th><th class="text-end">Gain / (Loss)</th>
                        <th class="text-end">Balance</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dollarEntries as $entry)
                    @php $result = (float) $entry->gain_loss; @endphp
                    <tr>
                        <td class="ps-3 text-nowrap">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>
                            <span class="badge {{ $entry->isIn() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $entry->sourceLabel() }}</span>
                            {{ $entry->description }}
                            @if($entry->reference)<small class="d-block text-muted">Ref: {{ $entry->reference }}</small>@endif
                            @if($entry->note)<small class="d-block text-muted">{{ $entry->note }}</small>@endif
                        </td>
                        <td class="text-end text-success">{{ $entry->isIn() ? $usd($entry->usd_amount) : '' }}</td>
                        <td class="text-end text-danger">{{ $entry->isIn() ? '' : $usd($entry->usd_amount) }}</td>
                        <td class="text-end">
                            {{ \App\Models\DollarTransaction::rate($entry->rate) }}
                            @if($entry->source === 'sale')<small class="d-block text-muted">sold at</small>@elseif(! $entry->isIn())<small class="d-block text-muted">at cost</small>@endif
                        </td>
                        <td class="text-end">৳ {{ number_format($entry->bdt_amount, 2) }}
                            @if($entry->source === 'sale')<small class="d-block text-muted">sold for ৳ {{ number_format($entry->proceeds(), 2) }}</small>@endif
                        </td>
                        <td class="text-end {{ $result > 0 ? 'text-success' : ($result < 0 ? 'text-danger' : '') }}">
                            @if($entry->source === 'sale'){{ $result < 0 ? '('.number_format(abs($result), 2).')' : number_format($result, 2) }}@endif
                        </td>
                        <td class="text-end fw-semibold">{{ $usd($entry->balance_after) }}</td>
                        <td class="text-end pe-3">
                            @if($entry->isManual())
                            @can('accounts.transactions.delete')
                            <form action="{{ route('dollars.delete', $entry->id) }}" method="POST" class="m-0">
                                @csrf @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-dollar-reverse" title="Reverse"><i class="ri-arrow-go-back-line"></i></button>
                            </form>
                            @endcan
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-3">No dollars yet. An LC payment from this account, or a deposit, adds them.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('accounts.deposit')
<div class="modal fade" id="dollarDepositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('dollars.deposit', $account->id) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Deposit Dollars — {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-6"><label class="form-label">Dollars <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" class="form-control" name="usd_amount" required></div>
                    <div class="col-6"><label class="form-label">Rate (৳ per $) <span class="text-danger">*</span></label><input type="number" step="0.0001" min="0.0001" class="form-control" name="rate" value="{{ $defaultRate }}" required><small class="text-muted">What the dollars are worth — they are costed at this.</small></div>
                    <div class="col-6"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="entry_date" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-6"><label class="form-label">Reference</label><input type="text" class="form-control" name="reference"></div>
                    <div class="col-12"><label class="form-label">Note</label><input type="text" class="form-control" name="note" placeholder="e.g. Customer paid in dollars"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success px-4">Deposit</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </form>
        </div>
    </div>
</div>
@endcan

@if((float) $account->usd_balance > 0)
@can('accounts.sell-dollars')
<div class="modal fade" id="dollarSellModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('dollars.sell', $account->id) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Sell Dollars for Taka — {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-12 small text-muted">Holds <strong>{{ $usd($account->usd_balance) }}</strong>, costing <strong>৳ {{ \App\Models\DollarTransaction::rate($avgRate) }}</strong> per dollar on average. The taka comes into this account.</div>
                    <div class="col-6"><label class="form-label">Dollars <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" max="{{ $account->usd_balance }}" class="form-control" name="usd_amount" id="sellUsd" value="{{ $account->usd_balance }}" required></div>
                    <div class="col-6"><label class="form-label">Sold at (৳ per $) <span class="text-danger">*</span></label><input type="number" step="0.0001" min="0.0001" class="form-control" name="rate" id="sellRate" value="{{ $defaultRate }}" required></div>
                    <div class="col-6"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="entry_date" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-6"><label class="form-label">Reference</label><input type="text" class="form-control" name="reference"></div>
                    <div class="col-12"><label class="form-label">Note</label><input type="text" class="form-control" name="note"></div>
                    <div class="col-12">
                        <div class="row g-2 text-center bg-light rounded p-2 m-0">
                            <div class="col-6"><small class="text-muted d-block">Taka received</small><strong id="sellProceeds">—</strong></div>
                            <div class="col-6"><small class="text-muted d-block">Exchange gain / (loss)</small><strong id="sellResult">—</strong></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success px-4">Sell</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </form>
        </div>
    </div>
</div>
@endcan

@can('accounts.fund-transfer')
<div class="modal fade" id="dollarTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('dollars.transfer', $account->id) }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Transfer Dollars from {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">To account <span class="text-danger">*</span></label>
                        <select class="form-control" name="to_account_id" required>
                            <option value="">-- Select account --</option>
                            <x-account-options :accounts="$accounts->where('id', '!=', $account->id)" />
                        </select>
                        <small class="text-muted">The dollars keep what they cost (৳ {{ \App\Models\DollarTransaction::rate($avgRate) }} each), so nothing is gained or lost.</small>
                    </div>
                    <div class="col-6"><label class="form-label">Dollars <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" max="{{ $account->usd_balance }}" class="form-control" name="usd_amount" required></div>
                    <div class="col-6"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="entry_date" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-12"><label class="form-label">Note</label><input type="text" class="form-control" name="note"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary px-4">Transfer</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </form>
        </div>
    </div>
</div>
@endcan
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const taka = n => '৳ ' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // A sale's taka and its gain or loss against what the dollars cost.
    const sellUsd = document.getElementById('sellUsd');
    if (sellUsd) {
        const held = {{ (float) $account->usd_balance }};
        const cost = {{ (float) $account->usd_cost }};
        const sellRate = document.getElementById('sellRate');
        const preview = function () {
            const dollars = parseFloat(sellUsd.value) || 0;
            const rate = parseFloat(sellRate.value) || 0;
            const proceeds = Math.round(dollars * rate * 100) / 100;
            const costOut = Math.abs(dollars - held) < 0.005 ? cost : Math.round(dollars * cost / held * 100) / 100;
            const result = Math.round((proceeds - costOut) * 100) / 100;
            document.getElementById('sellProceeds').textContent = dollars && rate ? taka(proceeds) : '—';
            const out = document.getElementById('sellResult');
            out.textContent = dollars && rate ? (result < 0 ? '(' + taka(result) + ') loss' : taka(result) + (result > 0 ? ' gain' : '')) : '—';
            out.className = result > 0 ? 'text-success' : (result < 0 ? 'text-danger' : '');
        };
        [sellUsd, sellRate].forEach(el => el.addEventListener('input', preview));
        preview();
    }

    document.querySelectorAll('.confirm-dollar-reverse').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Reverse this entry?', text: 'Its dollars, and any taka it brought in, are undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, reverse', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
