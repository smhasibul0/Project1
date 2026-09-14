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
                <small class="text-muted">
                    @if($lc->order)
                        {{ $lc->order->order_no }} · {{ $lc->order->customer->name ?? '—' }}
                    @else
                        Standalone LC — not linked to an order
                    @endif
                </small>
            </div>
            <div class="text-end">
                @can('lc.edit')<a href="{{ route('lc.edit', $lc->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>@endcan
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

                <div class="col-md-3"><small class="text-muted d-block">Order</small>
                    @if($lc->order_id)
                        <a href="{{ route('order.show', $lc->order_id) }}">{{ $lc->order->order_no ?? '—' }}</a>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Standalone</span>
                    @endif
                </div>
                <div class="col-md-3"><small class="text-muted d-block">Shipper</small>{{ $lc->shipper ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Opening Bank</small>{{ $lc->opening_bank ?: '—' }}</div>
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
        @endcan

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

        {{-- LC charges (feed the order's cost/profit) --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">LC Charges ({{ $lc->costs->count() }}) <small class="text-muted">— added to the order's cost alongside bank charges</small></h6>
                @can('lc.costs.create')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLcCostModal"><i class="ri-add-line me-1"></i> Add Charge</button>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr><th>Category</th><th>Title</th><th>Date</th><th>Account</th><th>Doc</th><th class="text-end">Amount</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5">Bank Charges (auto)</td><td class="text-end">{{ number_format($lc->bank_charges, 2) }}</td><td></td></tr>
                            @foreach($lc->costs as $c)
                            <tr>
                                <td>{{ $c->category->name ?? '—' }}</td>
                                <td>{{ $c->title }}</td>
                                <td>{{ $c->cost_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $c->paymentAccount->name ?? '—' }}</td>
                                <td>@if($c->attachment)<a href="{{ asset('upload/lc/'.$c->attachment) }}" target="_blank"><i class="ri-attachment-line"></i></a>@else—@endif</td>
                                <td class="text-end">{{ number_format($c->amount, 2) }}</td>
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
                            <tr class="table-light fw-semibold"><td colspan="5">Total LC Cost to Order</td><td class="text-end">{{ number_format($lc->lcCost(), 2) }}</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
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
                    <h5 class="modal-title">Add LC Charge</h5>
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
                        <input type="text" class="form-control" name="title" required placeholder="e.g. LC opening charge, Amendment fee">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.confirm-remove').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this charge?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
