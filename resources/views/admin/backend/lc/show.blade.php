@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'draft' => 'secondary', 'opened' => 'info', 'released' => 'success',
        'settled' => 'primary', 'cancelled' => 'danger',
    ];
    $rate = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
    $chargeRate = $lc->bankChargeRate();
    $chargeRateNote = match ($chargeRate['source']) {
        'payments' => 'at the bank\'s rate on its payments',
        'sell_rate' => 'at the USD Sell Rate',
        'estimate' => 'estimated at the day\'s rate — pay it or add a USD Sell Rate to fix it',
        default => 'no dollar rate yet — not counted until it is paid or has a USD Sell Rate',
    };
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">{{ $lc->lc_code }} <span class="badge bg-primary-subtle text-primary fs-12 align-middle">{{ $lc->typeName() }}</span> @if($lc->lc_number)<small class="text-muted">/ {{ $lc->lc_number }}</small>@endif</h4>
                <small class="text-muted">
                    @if($lc->order)
                        {{ $lc->order->order_no }} · {{ $lc->order->customer->name ?? '—' }}
                    @else
                        Standalone {{ $lc->typeLabel() }} — not linked to an order
                    @endif
                </small>
            </div>
            <div class="text-end">
                @can('lc.edit')<a href="{{ route('lc.edit', $lc->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>@endcan
                <a href="{{ route('lc.index', $lc->type) }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ $lc->typeLabel() }} &amp; Purchase Invoice</h6>
                <span class="badge bg-{{ $statusColors[$lc->lc_status] ?? 'secondary' }}">{{ $lc->statusLabel() }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">PI Date</small><strong>{{ $lc->pi_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PI No</small><strong>{{ $lc->pi_no ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">{{ $lc->typeLabel() }} Number</small><strong>{{ $lc->lc_number ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PI Document</small>
                    @if($lc->pi_document)<a href="{{ asset('upload/lc/'.$lc->pi_document) }}" target="_blank"><i class="ri-attachment-line me-1"></i>View</a>@else<span>—</span>@endif
                </div>

                <div class="col-md-3"><small class="text-muted d-block">Order</small>
                    @if($lc->order_id)
                        <a href="{{ route('order.show', $lc->order_id) }}">{{ $lc->order->order_no ?? '—' }}</a>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Standalone</span>
                    @endif
                </div>
                <div class="col-md-3"><small class="text-muted d-block">Shipper</small>{{ $lc->shipper ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">{{ $lc->type === 'lc' ? 'Opening Bank' : 'Bank' }}</small>{{ $lc->opening_bank ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Container No</small>{{ $lc->container_no ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">Commodity</small>{{ $lc->commodity ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">USD Sell Rate</small>{{ $lc->usd_sell_rate !== null ? rtrim(rtrim(number_format($lc->usd_sell_rate, 4), '0'), '.') : '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">USD Sell Date</small>{{ $lc->usd_sell_date?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Released Date</small>{{ $lc->released_date?->format('d M Y') ?: '—' }}</div>
            </div>
        </div>

        {{-- Quick status update --}}
        @can('lc.update-status')
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Update Status</h6></div>
            <div class="card-body">
                <form action="{{ route('lc.status', $lc->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="lc_status" required>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($lc->lc_status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Released Date <small class="text-muted">(auto-set on release)</small></label>
                        <input type="date" class="form-control" name="released_date" value="{{ optional($lc->released_date)->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary"><i class="ri-refresh-line me-1"></i> Update Status</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Amounts &amp; Charges</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><th>Inv. Amount (Sent)</th><td class="text-end">{{ $lc->currency }} {{ number_format($lc->invoice_amount, 2) }}</td></tr>
                            <tr><th>Net Amt. Received</th><td class="text-end">{{ $lc->net_amount_received !== null ? $lc->currency.' '.number_format($lc->net_amount_received, 2) : '—' }}</td></tr>
                            <tr class="table-light fw-semibold"><th>Bank Charges</th><td class="text-end">{{ $lc->currency }} {{ number_format($lc->bank_charges, 2) }}</td></tr>
                            @if($lc->isDollar() && (float) $lc->bank_charges > 0)
                            <tr class="{{ $lc->needsBankRate() ? 'table-warning' : '' }}">
                                <th>Bank Charges in Taka<small class="d-block text-muted fw-normal">{{ $chargeRateNote }}</small></th>
                                <td class="text-end">
                                    @if($chargeRate['rate'])৳ {{ number_format($lc->bankChargesInTaka(), 2) }}<small class="d-block text-muted">@ {{ $rate($chargeRate['rate']) }}</small>@else—@endif
                                </td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Remarks</h6></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $lc->remarks ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Dollar payments: taka buys dollars that the account keeps — no cost, no gain or loss --}}
        @if($lc->isDollar())
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">{{ $lc->typeLabel() }} Payments ({{ $lc->payments->count() }}) <small class="text-muted">— taka buys the dollars, which stay in the account</small></h6>
                @can('lc.payments.create')
                @if($lc->usdDue() > 0)
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payLcModal"><i class="ri-money-dollar-circle-line me-1"></i> Pay {{ $lc->typeLabel() }}</button>
                @endif
                @endcan
            </div>
            <div class="card-body border-bottom row g-3 text-center">
                <div class="col-6 col-md"><small class="text-muted d-block">Invoice</small><strong>${{ number_format($lc->invoice_amount, 2) }}</strong></div>
                <div class="col-6 col-md"><small class="text-muted d-block">Paid</small><strong class="text-success">${{ number_format($lc->usdPaid(), 2) }}</strong></div>
                <div class="col-6 col-md"><small class="text-muted d-block">Due</small><strong class="{{ $lc->usdDue() > 0 ? 'text-danger' : '' }}">${{ number_format($lc->usdDue(), 2) }}</strong></div>
                <div class="col-6 col-md"><small class="text-muted d-block">Taka Paid</small><strong>৳ {{ number_format($lc->payments->sum('bdt_amount'), 2) }}</strong>@if($lc->averageBankRate())<small class="d-block text-muted">avg @ {{ $rate($lc->averageBankRate()) }}</small>@endif</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr><th>Date</th><th>Dollars kept in</th><th class="text-end">Dollars</th><th class="text-end">Bank Rate</th><th class="text-end">Taka Paid</th><th>Reference</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($lc->payments as $p)
                            <tr>
                                <td>{{ $p->paid_on->format('d M Y') }}</td>
                                <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                                <td class="text-end">${{ number_format($p->usd_amount, 2) }}</td>
                                <td class="text-end">{{ $rate($p->bank_rate) }}</td>
                                <td class="text-end">৳ {{ number_format($p->bdt_amount, 2) }}</td>
                                <td>{{ $p->reference ?: '—' }}@if($p->note)<small class="d-block text-muted">{{ $p->note }}</small>@endif</td>
                                <td class="text-end">
                                    @can('lc.payments.delete')
                                    <form action="{{ route('lc.payment.delete', [$lc->id, $p->id]) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-reverse" title="Reverse payment"><i class="ri-arrow-go-back-line"></i></button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- LC charges (feed the order's cost/profit) --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">{{ $lc->typeLabel() }} Charges ({{ $lc->costs->count() }}) <small class="text-muted">— added to the order's cost alongside bank charges</small></h6>
                @can('lc.costs.create')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLcCostModal"><i class="ri-add-line me-1"></i> Add Charge</button>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr><th>Category</th><th>Title</th><th>Date</th><th>Account</th><th>Doc</th><th class="text-end">Amount (৳)</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5">Bank Charges (auto)
                                    @if($lc->isDollar() && (float) $lc->bank_charges > 0)<small class="text-muted">— ${{ number_format($lc->bank_charges, 2) }}{{ $chargeRate['rate'] ? ' @ '.$rate($chargeRate['rate']) : '' }}, {{ $chargeRateNote }}</small>@endif
                                </td>
                                <td class="text-end">{{ number_format($lc->bankChargesInTaka(), 2) }}</td><td></td>
                            </tr>
                            @foreach($lc->costs as $c)
                            <tr>
                                <td>{{ $c->category->name ?? '—' }}</td>
                                <td>{{ $c->title }}</td>
                                <td>{{ $c->cost_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $c->paymentAccount->name ?? '—' }}</td>
                                <td>@if($c->attachment)<a href="{{ asset('upload/lc/'.$c->attachment) }}" target="_blank"><i class="ri-attachment-line"></i></a>@else—@endif</td>
                                <td class="text-end">{{ number_format($c->amount, 2) }}@if($c->usd_amount !== null)<small class="d-block text-muted">${{ number_format($c->usd_amount, 2) }} @ {{ $rate($c->usd_rate) }}</small>@endif</td>
                                <td class="text-end">
                                    @can('lc.costs.delete')
                                    <form action="{{ route('lc.cost.delete', [$lc->id, $c->id]) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 confirm-remove"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold"><td colspan="5">Total {{ $lc->typeLabel() }} Cost to Order</td><td class="text-end">৳ {{ number_format($lc->lcCost(), 2) }}</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <x-activity-history :record="$lc" />
    </div>
</div>

{{-- ===================== Add LC Charge modal ===================== --}}
@can('lc.costs.create')
<div class="modal fade" id="addLcCostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('lc.cost.store', $lc->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add {{ $lc->typeLabel() }} Charge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="cost_category_id">
                            <option value="">-- Uncategorized --</option>
                            @foreach($costCategories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. {{ $lc->typeLabel() }} opening charge, Amendment fee">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cost Date</label>
                        <input type="date" class="form-control" name="cost_date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Pay from Account <small class="text-muted">(debits the account ledger)</small></label>
                        <select class="form-control" name="payment_account_id">
                            <option value="">-- None (no ledger entry) --</option>
                            <x-account-options :accounts="$accounts" />
                        </select>
                    </div>
                    <x-currency-choice direction="out" />
                    <div class="col-md-6">
                        <label class="form-label">Attach Document</label>
                        <input type="file" class="form-control" name="attachment" accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Note</label>
                        <input type="text" class="form-control" name="note">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary px-4">Save Charge</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- ===================== Pay LC modal ===================== --}}
@if($lc->isDollar() && $lc->usdDue() > 0)
@can('lc.payments.create')
<div class="modal fade" id="payLcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('lc.payment.store', $lc->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Pay {{ $lc->lc_code }} <small class="text-muted">— ${{ number_format($lc->usdDue(), 2) }} due</small></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="paid_on" id="payDate" value="{{ old('paid_on', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Pay from Account <span class="text-danger">*</span></label>
                        <select class="form-control" name="payment_account_id" required>
                            <option value="">-- Select account --</option>
                            <x-account-options :accounts="$accounts" :selected="old('payment_account_id')" />
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dollars <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $lc->usdDue() }}" class="form-control" name="usd_amount" id="payUsd" value="{{ old('usd_amount', $lc->usdDue()) }}" required>
                        <small class="text-muted">Pay all or part of what is due.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bank's Rate <span class="text-danger">*</span></label>
                        <input type="number" step="0.0001" min="0.0001" class="form-control" name="bank_rate" id="payBankRate" value="{{ old('bank_rate') }}" required>
                        <small class="text-muted" id="payBankRateNote">What the bank charged per dollar.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reference</label>
                        <input type="text" class="form-control" name="reference" value="{{ old('reference') }}" placeholder="e.g. bank advice no.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Note</label>
                        <input type="text" class="form-control" name="note" value="{{ old('note') }}">
                    </div>
                    <div class="col-12">
                        <div class="row g-2 text-center bg-light rounded p-2 m-0">
                            <div class="col-6"><small class="text-muted d-block">Taka leaving the account</small><strong id="payBdtPreview">—</strong></div>
                            <div class="col-6"><small class="text-muted d-block">Dollars kept in the account</small><strong id="payUsdPreview">—</strong></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success px-4">Pay</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.confirm-remove').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this charge?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    document.querySelectorAll('.confirm-reverse').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Reverse this payment?', text: 'Its dollars leave the account and the taka goes back.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, reverse', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    const taka = n => '৳ ' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    // The dollar rate in force on a date — that day's own, or the latest before it.
    async function dayRate(date, input, note) {
        if (!date) { return; }
        try {
            const res = await fetch('{{ route('exchange.rate.lookup') }}?date=' + encodeURIComponent(date), { headers: { 'Accept': 'application/json' } });
            const rate = res.ok ? await res.json() : null;
            if (!rate || rate.usd_rate === null) { note.textContent = 'No dollar rate set yet — enter it.'; return; }
            input.value = rate.usd_rate;
            note.textContent = rate.is_exact ? 'Rate for ' + fmtDate(rate.rate_date) + '.' : 'No rate for this day — using ' + fmtDate(rate.rate_date) + '.';
            input.dispatchEvent(new Event('input'));
        } catch (e) {
            note.textContent = 'Could not load the day\'s rate — enter it.';
        }
    }

    const payDate = document.getElementById('payDate');
    if (payDate) {
        const usd = document.getElementById('payUsd');
        const bank = document.getElementById('payBankRate');
        const bdtOut = document.getElementById('payBdtPreview');
        const usdOut = document.getElementById('payUsdPreview');

        const preview = function () {
            const dollars = parseFloat(usd.value) || 0;
            const bankValue = parseFloat(bank.value) || 0;
            bdtOut.textContent = dollars && bankValue ? taka(dollars * bankValue) : '—';
            usdOut.textContent = dollars ? '$' + dollars.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
        };

        [usd, bank].forEach(el => el.addEventListener('input', preview));
        // The day's rate as a starting point for the bank's; type over it with the bank's own.
        payDate.addEventListener('change', () => { if (!bank.dataset.touched) { dayRate(payDate.value, bank, document.getElementById('payBankRateNote')); } });
        bank.addEventListener('input', e => { if (e.isTrusted) { bank.dataset.touched = '1'; } });
        if (!bank.value) { dayRate(payDate.value, bank, document.getElementById('payBankRateNote')); }
        preview();

        @if(old('bank_rate'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('payLcModal')).show();
        @endif
    }
});
</script>
@endsection
