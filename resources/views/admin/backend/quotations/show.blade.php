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
                    {{-- Customer accepted -> can be turned into an order --}}
                    @if($quotation->status === 'accepted')
                        @can('orders.manage')
                        <form action="{{ route('order.from.quotation', $quotation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="ri-arrow-right-line me-1"></i> Convert to Order</button>
                        </form>
                        @endcan
                    @endif

                    {{-- Customer wants to negotiate -> re-quote or deny --}}
                    @if($quotation->status === 'negotiating')
                        <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-primary btn-sm"><i class="ri-price-tag-3-line me-1"></i> Re-quote</a>
                        <form action="{{ route('quotation.deny', $quotation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm deny-btn"><i class="ri-close-line me-1"></i> Deny</button>
                        </form>
                    @endif

                    {{-- Still requested / draft -> respond/edit --}}
                    @if(in_array($quotation->status, ['requested', 'draft', 'quoted', 'rejected']))
                        <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> {{ $quotation->status === 'requested' ? 'Respond' : 'Edit' }}</a>
                    @endif
                @endif
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
                <div class="col-md-3">
                    <small class="text-muted d-block">Packing List</small>
                    @if($quotation->packing_list_path)
                        <a href="{{ asset('upload/quotation/'.$quotation->packing_list_path) }}" class="fw-semibold"><i class="ri-file-excel-2-line me-1"></i>Download</a>
                    @else
                        <span class="text-danger">Missing</span>
                    @endif
                </div>
                @if($quotation->remarks)<div class="col-12"><small class="text-muted d-block">Remarks</small>{{ $quotation->remarks }}</div>@endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Products ({{ $quotation->items->count() }})</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr>
                                <th>#</th><th>Item</th><th>Category</th><th>HS Code</th><th>Transport</th><th>Loading</th><th>Packing</th>
                                <th class="text-end">Qty</th><th class="text-end">CBM</th>
                                <th>Supplier</th><th class="text-end">Sup. Price</th><th class="text-end">Our Price</th>
                                <th class="text-end">Line Total</th><th class="text-end">Profit</th><th class="text-end">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->product->name ?? ($item->description ?: ($item->remarks ?: '—')) }}@if($item->product)<span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.6rem;">catalogue</span>@endif</td>
                                <td>{{ $item->category->name ?? '—' }}</td>
                                <td>{{ $item->hs_code ?: '—' }}</td>
                                <td>{{ $item->transportationMode->name ?? '—' }}</td>
                                <td>{{ $item->country_of_loading ?: '—' }}</td>
                                <td>{{ $item->packingType->name ?? '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                                <td>{{ $item->supplier->business_name ?? $item->supplier->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->supplier_asking_price, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->our_asking_price, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->line_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->total_profit, 2) }}</td>
                                <td class="text-end">{{ number_format($item->profit_margin, 2) }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="12" class="text-end">Totals:</td>
                                <td class="text-end">৳ {{ number_format($quotation->grand_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->total_profit, 2) }}</td>
                                <td class="text-end">{{ number_format($quotation->profit_margin, 2) }}%</td>
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
                                <th>Item</th><th>HS Code</th><th class="text-end">Assessable Value</th>
                                <th class="text-end">CD %</th><th class="text-end">RD %</th><th class="text-end">SD %</th>
                                <th class="text-end">VAT %</th><th class="text-end">AIT %</th><th class="text-end">AT %</th>
                                <th class="text-end">Duty &amp; Taxes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->items as $item)
                            <tr>
                                <td>{{ $item->product->name ?? ($item->description ?: '—') }}</td>
                                <td>{{ $item->hs_code ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->assessable_value, 2) }}</td>
                                <td class="text-end">{{ number_format($item->cd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->rd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->sd_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->vat_rate, 2) }}</td>
                                <td class="text-end">{{ number_format($item->ait_rate, 2) }}</td>
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
            <div class="card-header"><h6 class="mb-0">Predicted Expenses</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr><th>Type</th><th>Category</th><th>Title</th><th>Note</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->expenses as $expense)
                            <tr>
                                <td><span class="badge bg-{{ $expense->expense_group === 'lc' ? 'info' : 'secondary' }}">{{ $expense->expense_group === 'lc' ? 'LC Cost' : 'Custom' }}</span></td>
                                <td>{{ $expense->category->name ?? '—' }}</td>
                                <td>{{ $expense->title }}</td>
                                <td>{{ $expense->note ?: '—' }}</td>
                                <td class="text-end">৳ {{ number_format($expense->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="4" class="text-end">Total Predicted Expenses:</td>
                                <td class="text-end">৳ {{ number_format($quotation->expenses->sum('amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ===================== Cost projection summary ===================== --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Cost Projection Summary</h6></div>
            <div class="card-body row justify-content-end">
                @php
                    $goodsCost = $quotation->items->sum(fn ($i) => (float) $i->supplier_asking_price * (float) $i->package_quantity);
                    $lcTotal = $quotation->expenses->where('expense_group', 'lc')->sum('amount');
                    $customTotal = $quotation->expenses->where('expense_group', 'custom')->sum('amount');
                @endphp
                <div class="col-md-6">
                    <table class="table table-sm mb-0">
                        <tr><th class="text-end">Goods Cost (supplier):</th><td class="text-end" style="width:35%">৳ {{ number_format($goodsCost, 2) }}</td></tr>
                        <tr><th class="text-end">Duty &amp; Taxes (TTI):</th><td class="text-end">৳ {{ number_format($quotation->total_duty, 2) }}</td></tr>
                        <tr>
                            <th class="text-end">Predicted Freight
                                @if($quotation->freight_type === 'lcl')<small class="text-muted fw-normal">(LCL @ {{ number_format((float) $quotation->freight_rate, 2) }}/CBM)</small>
                                @elseif($quotation->freight_type === 'fcl')<small class="text-muted fw-normal">(FCL{{ $quotation->freight_container_size ? ' '.$quotation->freight_container_size : '' }})</small>
                                @endif:
                            </th>
                            <td class="text-end">৳ {{ number_format($quotation->freight_amount, 2) }}</td>
                        </tr>
                        <tr><th class="text-end">Predicted LC Costs:</th><td class="text-end">৳ {{ number_format($lcTotal, 2) }}</td></tr>
                        <tr><th class="text-end">Custom Expenses:</th><td class="text-end">৳ {{ number_format($customTotal, 2) }}</td></tr>
                        <tr class="table-light"><th class="text-end">Projected Total Cost:</th><td class="text-end">৳ {{ number_format($quotation->projected_cost_total, 2) }}</td></tr>
                        <tr><th class="text-end">Asking Total:</th><td class="text-end">৳ {{ number_format($quotation->grand_total, 2) }}</td></tr>
                        <tr class="fw-bold {{ $quotation->projected_profit < 0 ? 'text-danger' : '' }}">
                            <th class="text-end">Projected Net Profit:</th>
                            <td class="text-end">৳ {{ number_format($quotation->projected_profit, 2) }}
                                ({{ $quotation->grand_total > 0 ? number_format(($quotation->projected_profit / $quotation->grand_total) * 100, 2) : '0.00' }}%)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
