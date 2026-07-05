@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'draft' => 'secondary', 'opened' => 'info', 'released' => 'success',
        'settled' => 'primary', 'cancelled' => 'danger',
    ];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">LC {{ $lc->lc_code }} @if($lc->lc_number)<small class="text-muted">/ {{ $lc->lc_number }}</small>@endif</h4>
                <small class="text-muted">{{ $lc->order->order_no ?? '—' }} · {{ $lc->order->customer->name ?? '—' }}</small>
            </div>
            <div class="text-end">
                <a href="{{ route('lc.edit', $lc->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>
                <a href="{{ route('lc.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">LC &amp; Purchase Invoice</h6>
                <span class="badge bg-{{ $statusColors[$lc->lc_status] ?? 'secondary' }}">{{ $lc->statusLabel() }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">PI Date</small><strong>{{ $lc->pi_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PI No</small><strong>{{ $lc->pi_no ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">LC Number</small><strong>{{ $lc->lc_number ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PI Document</small>
                    @if($lc->pi_document)<a href="{{ asset('upload/lc/'.$lc->pi_document) }}" target="_blank"><i class="ri-attachment-line me-1"></i>View</a>@else<span>—</span>@endif
                </div>

                <div class="col-md-3"><small class="text-muted d-block">Order</small><a href="{{ route('order.show', $lc->order_id) }}">{{ $lc->order->order_no ?? '—' }}</a></div>
                <div class="col-md-3"><small class="text-muted d-block">Supplier</small>{{ $lc->supplier->name ?? '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Opening Bank</small>{{ $lc->opening_bank ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Container No</small>{{ $lc->container_no ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">Commodity</small>{{ $lc->commodity ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">USD Sell Rate</small>{{ $lc->usd_sell_rate !== null ? rtrim(rtrim(number_format($lc->usd_sell_rate, 4), '0'), '.') : '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">USD Sell Date</small>{{ $lc->usd_sell_date?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Released Date</small>{{ $lc->released_date?->format('d M Y') ?: '—' }}</div>
            </div>
        </div>

        {{-- Quick status update --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Update LC Status</h6></div>
            <div class="card-body">
                <form action="{{ route('lc.status', $lc->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">LC Status</label>
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

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Amounts &amp; Charges</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><th>Inv. Amount (Sent)</th><td class="text-end">{{ $lc->currency }} {{ number_format($lc->invoice_amount, 2) }}</td></tr>
                            <tr><th>Net Amt. Received</th><td class="text-end">{{ $lc->net_amount_received !== null ? $lc->currency.' '.number_format($lc->net_amount_received, 2) : '—' }}</td></tr>
                            <tr class="table-light fw-semibold"><th>Bank Charges</th><td class="text-end">{{ $lc->currency }} {{ number_format($lc->bank_charges, 2) }}</td></tr>
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
    </div>
</div>
@endsection
