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
                <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>
                <a href="{{ route('quotations.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Quotation No</small><strong>{{ $quotation->quotation_no }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Query Date</small><strong>{{ $quotation->query_received_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Customer</small><strong>{{ $quotation->customer->name ?? '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Status</small><span class="badge bg-info text-capitalize">{{ $quotation->status }}</span> @if($quotation->submitted_to_customer)<span class="badge bg-success">Submitted</span>@endif</div>
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
                                <th>#</th><th>Category</th><th>HS Code</th><th>Transport</th><th>Loading</th><th>Packing</th>
                                <th class="text-end">Qty</th><th class="text-end">CBM</th>
                                <th>Supplier</th><th class="text-end">Sup. Price</th><th class="text-end">Our Price</th>
                                <th class="text-end">Line Total</th><th class="text-end">Profit</th><th class="text-end">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quotation->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
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
                                <td colspan="11" class="text-end">Totals:</td>
                                <td class="text-end">৳ {{ number_format($quotation->grand_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($quotation->total_profit, 2) }}</td>
                                <td class="text-end">{{ number_format($quotation->profit_margin, 2) }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
