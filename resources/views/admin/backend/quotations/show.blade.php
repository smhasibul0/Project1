@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Quotation {{ $quotation->quotation_no }}</h4>
                <small class="text-muted">{{ $quotation->customer->name ?? '—' }}</small>
            </div>
            <div class="text-end">
                @if($quotation->status === 'converted')
                    <span class="badge bg-success">Converted to Order</span>
                @else
                    {{-- Any quotation can be turned into an order; accepted is just the usual point. --}}
                    @can('orders.create')
                    <form action="{{ route('order.from.quotation', $quotation->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"><i class="ri-arrow-right-line me-1"></i> Convert to Order</button>
                    </form>
                    @endcan

                    {{-- Customer wants to negotiate -> re-quote or deny --}}
                    @if($quotation->status === 'negotiating')
                        @can('quotations.edit')
                        <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-primary btn-sm"><i class="ri-price-tag-3-line me-1"></i> Re-quote</a>
                        @endcan
                        @can('quotations.deny')
                        <form action="{{ route('quotation.deny', $quotation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm deny-btn"><i class="ri-close-line me-1"></i> Deny</button>
                        </form>
                        @endcan
                    @endif

                    {{-- Still requested / draft -> respond/edit --}}
                    @if(in_array($quotation->status, ['requested', 'draft', 'quoted', 'rejected']))
                        @can('quotations.edit')
                        <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> {{ $quotation->status === 'requested' ? 'Respond' : 'Edit' }}</a>
                        @endcan
                    @endif
                @endif
                @can('quotations.print')
                <a href="{{ route('quotation.print', $quotation->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="ri-file-pdf-2-line me-1"></i> Export PDF
                </a>
                @endcan
                <a href="{{ route('quotations.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Quotation No</small><strong>{{ $quotation->quotation_no }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Query Date</small><strong>{{ $quotation->query_received_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Customer</small><strong>{{ $quotation->customer->name ?? '—' }}</strong></div>
                @php $qc = ['draft' => 'secondary', 'requested' => 'warning', 'quoted' => 'info', 'accepted' => 'success', 'negotiating' => 'primary', 'rejected' => 'danger', 'converted' => 'dark']; @endphp
                <div class="col-md-3"><small class="text-muted d-block">Status</small><span class="badge bg-{{ $qc[$quotation->status] ?? 'secondary' }}">{{ $quotation->statusLabel() }}</span>@if($quotation->status === 'accepted')<span class="badge bg-success-subtle text-success ms-1">by customer</span>@endif</div>
                <div class="col-md-3"><small class="text-muted d-block">Transport Mode</small><strong>{{ $quotation->transportationMode->name ?? '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Country of Loading</small><strong>{{ $quotation->country_of_loading ?: '—' }}</strong></div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Packing List</small>
                    @if($quotation->packing_list_path)
                        <a href="{{ asset('upload/quotation/'.$quotation->packing_list_path) }}" class="fw-semibold"><i class="ri-file-excel-2-line me-1"></i>Download</a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
                @if($quotation->remarks)<div class="col-12"><small class="text-muted d-block">Remarks</small>{{ $quotation->remarks }}</div>@endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Shipment Items ({{ $quotation->items->count() }})</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr>
                                <th>#</th><th>HS Code</th><th>Description</th><th>Packing</th>
                                <th class="text-end">Qty</th><th class="text-end">CBM</th>
                                <th class="text-end">Declared Value</th><th class="text-end">Duty</th>
                                <th class="text-end">Charge</th><th class="text-end">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="fw-semibold text-nowrap">{{ $item->hs_code ?: '—' }}</td>
                                <td>{{ $item->description ?: ($item->hsCodeRecord->description ?? '—') }}</td>
                                <td>{{ $item->packingType->name ?? '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->declared_value, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->duty_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->line_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->total_profit, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="5" class="text-end">Totals:</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($quotation->total_cbm, 4), '0'), '.') }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->items->sum('declared_value'), 2) }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->total_duty, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->customer_charge, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->total_profit, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===================== Duty & tax projection ===================== --}}
        @if($quotation->items->sum('duty_amount') > 0)
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Duty &amp; Tax Projection (BD Customs)</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr>
                                <th>HS Code</th><th>Description</th><th class="text-end">Assessable Value</th>
                                <th class="text-end">CD %</th><th class="text-end">SD %</th><th class="text-end">VAT %</th>
                                <th class="text-end">AIT %</th><th class="text-end">RD %</th><th class="text-end">AT %</th>
                                <th class="text-end">Duty &amp; Taxes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->items as $item)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $item->hs_code ?: '—' }}</td>
                                <td>{{ $item->description ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->assessable_value, 2) }}</td>
                                <td class="text-end">{{ number_format($item->cd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->sd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->vat_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->ait_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->rd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->at_rate, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->duty_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="9" class="text-end">Total Duty &amp; Taxes:</td>
                                <td class="text-end">৳ {{ number_format($quotation->total_duty, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ===================== Predicted expenses ===================== --}}
        @if($quotation->expenses->isNotEmpty())
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Additional Costs</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr><th>Category</th><th>Title</th><th>Note</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->expenses as $expense)
                            <tr>
                                <td>{{ $expense->category->name ?? '—' }}</td>
                                <td>{{ $expense->title }}</td>
                                <td>{{ $expense->note ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($expense->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total Additional Costs:</td>
                                <td class="text-end">৳ {{ number_format($quotation->expenses->sum('amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ===================== Total cost vs. customer price ===================== --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Total Cost vs. Customer Price</h6></div>
            <div class="card-body row g-3">
                @php $expenseTotal = $quotation->expenses->sum('amount'); @endphp
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <thead><tr><th colspan="2" class="text-uppercase small text-muted">What this shipment costs us</th></tr></thead>
                        <tr>
                            <th class="text-end">Freight
                                @if($quotation->freight_type === 'lcl')<small class="text-muted fw-normal">(LCL @ ৳{{ number_format((float) $quotation->freight_rate, 2) }}/CBM)</small>
                                @elseif($quotation->freight_type === 'fcl')<small class="text-muted fw-normal">(FCL{{ $quotation->freight_container_size ? ' '.$quotation->freight_container_size : '' }})</small>
                                @endif:
                            </th>
                            <td class="text-end" style="width:35%">৳ {{ number_format($quotation->freight_amount, 2) }}</td>
                        </tr>
                        <tr><th class="text-end">Duty &amp; Taxes (TTI):</th><td class="text-end">৳ {{ number_format($quotation->total_duty, 2) }}</td></tr>
                        <tr><th class="text-end">Additional Costs:</th><td class="text-end">৳ {{ number_format($expenseTotal, 2) }}</td></tr>
                        <tr class="table-light fw-bold"><th class="text-end">Total Cost:</th><td class="text-end">৳ {{ number_format($quotation->projected_cost_total, 2) }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <thead><tr><th colspan="2" class="text-uppercase small text-muted">What we ask the customer</th></tr></thead>
                        <tr><th class="text-end">Rate per CBM:</th><td class="text-end" style="width:35%">৳ {{ number_format($quotation->sell_rate_per_cbm, 2) }}</td></tr>
                        <tr><th class="text-end">Total CBM:</th><td class="text-end">{{ rtrim(rtrim(number_format($quotation->total_cbm, 4), '0'), '.') }}</td></tr>
                        <tr class="table-light fw-bold"><th class="text-end">Customer Price:</th><td class="text-end">৳ {{ number_format($quotation->customer_charge, 2) }}</td></tr>
                        <tr class="fw-bold {{ $quotation->projected_profit < 0 ? 'text-danger' : 'text-success' }}">
                            <th class="text-end">Projected Profit:</th>
                            <td class="text-end">৳ {{ number_format($quotation->projected_profit, 2) }}
                                ({{ $quotation->customer_charge > 0 ? number_format(($quotation->projected_profit / $quotation->customer_charge) * 100, 2) : '0.00' }}%)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
