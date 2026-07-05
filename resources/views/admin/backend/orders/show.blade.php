@extends('admin.admin_master')
@section('admin')

@php
    $statusLabels = [
        'pending' => 'Pending', 'sourcing' => 'Sourcing', 'at_china_warehouse' => 'At China Warehouse',
        'shipped' => 'Shipped', 'at_port' => 'At Port', 'at_bd_warehouse' => 'At BD Warehouse',
        'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Order {{ $order->order_no }}</h4>
                <small class="text-muted">{{ $order->customer->name ?? '—' }}</small>
            </div>
            <div class="text-end">
                <a href="{{ route('order.invoice', $order->id) }}" target="_blank" class="btn btn-success btn-sm"><i class="ri-file-text-line me-1"></i> Invoice</a>
                @can('orders.manage')<a href="{{ route('order.edit', $order->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>@endcan
                <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-3"><small class="text-muted d-block">Order No</small><strong>{{ $order->order_no }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Order Date</small><strong>{{ $order->order_date?->format('d M Y') ?: '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Quotation</small><strong>{{ $order->quotation?->quotation_no ?? '—' }}</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Goods Status</small><span class="badge bg-info">{{ $statusLabels[$order->goods_status] ?? $order->goods_status }}</span></div>

                <div class="col-md-3"><small class="text-muted d-block">Shipment No</small>{{ $order->shipment_no ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Shipping Mark</small>{{ $order->shipping_mark ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Transport</small>{{ $order->transportationMode->name ?? '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Country of Loading</small>{{ $order->country_of_loading ?: '—' }}</div>

                <div class="col-md-3"><small class="text-muted d-block">Handover Date</small>{{ $order->goods_handover_date?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Port Arrival</small>{{ $order->port_arrival_date?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">BD Warehouse</small>{{ $order->bd_warehouse_date?->format('d M Y') ?: '—' }}</div>
                <div class="col-md-3"><small class="text-muted d-block">Delivery Days</small>{{ $order->total_delivery_days ?? '—' }}</div>
            </div>
        </div>

        {{-- Tracking + status update --}}
        <div class="row g-3">
            @can('orders.update-status')
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Update Status</h6></div>
                    <div class="card-body">
                        <form action="{{ route('order.status', $order->id) }}" method="POST">
                            @csrf
                            <label class="form-label">Goods Status</label>
                            <select class="form-control mb-2" name="goods_status" required>
                                @foreach(\App\Models\Order::goodsStatuses() as $key => $label)
                                    <option value="{{ $key }}" @selected($order->goods_status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="form-label">Note (optional)</label>
                            <textarea class="form-control mb-2" name="note" rows="2" placeholder="e.g. Received 12 cartons at BD warehouse"></textarea>
                            <button type="submit" class="btn btn-primary w-100"><i class="ri-refresh-line me-1"></i> Update &amp; Log</button>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
            <div class="@can('orders.update-status') col-lg-7 @else col-12 @endcan">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Tracking Timeline</h6>
                        <a href="{{ route('order.track', ['order_no' => $order->order_no]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ri-external-link-line me-1"></i> Public link</a>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 order-timeline">
                            @forelse($order->tracking as $t)
                            <li class="d-flex gap-2 pb-3">
                                <div><span class="badge bg-info">{{ $statusLabels[$t->status] ?? $t->status }}</span></div>
                                <div class="small">
                                    <div class="text-muted">{{ $t->created_at->format('d M Y, h:i A') }} @if($t->changedBy) · {{ $t->changedBy->name }} @endif</div>
                                    @if($t->note)<div>{{ $t->note }}</div>@endif
                                </div>
                            </li>
                            @empty
                            <li class="text-muted text-center py-3">No tracking updates yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Products ({{ $order->items->count() }})</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr>
                                <th>#</th><th>Item</th><th>HS Code</th><th class="text-end">Qty</th><th>Unit</th>
                                <th class="text-end">Pkg</th><th class="text-end">Net Wt</th><th class="text-end">CBM</th>
                                <th class="text-end">Sup. Price</th><th class="text-end">Our Price</th><th class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->item_description ?: ($item->category->name ?? '—') }}</td>
                                <td>{{ $item->hs_code ?: '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td>{{ $item->unit->short_name ?? $item->unit->name ?? '—' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ $item->net_weight ? rtrim(rtrim(number_format($item->net_weight, 3), '0'), '.') : '—' }}</td>
                                <td class="text-end">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->supplier_asking_price, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->our_asking_price, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->line_total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Expenses</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            @forelse($order->expenses as $e)
                            <tr><td>{{ $e->title }}</td><td class="text-end">৳ {{ number_format($e->amount, 2) }}</td></tr>
                            @empty
                            <tr><td class="text-muted text-center py-3" colspan="2">No expenses</td></tr>
                            @endforelse
                            <tr class="table-light fw-semibold"><td>Total Expenses</td><td class="text-end">৳ {{ number_format($order->total_expense, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Financials</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><th>Subtotal</th><td class="text-end">৳ {{ number_format($order->subtotal, 2) }}</td></tr>
                            <tr><th>Discount</th><td class="text-end">{{ $order->discount_type === 'percentage' ? number_format($order->discount_value, 2).'%' : '৳ '.number_format($order->discount_value, 2) }}</td></tr>
                            <tr><th>Total Amount</th><td class="text-end fw-semibold">৳ {{ number_format($order->total_amount, 2) }}</td></tr>
                            <tr><th>Received ({{ ucfirst($order->payment_status) }})</th><td class="text-end">৳ {{ number_format($order->received_amount, 2) }}</td></tr>
                            <tr><th>Due</th><td class="text-end">৳ {{ number_format($order->due_amount, 2) }}</td></tr>
                            <tr class="table-light"><th>Profit</th><td class="text-end fw-semibold">৳ {{ number_format($order->profit, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payments ({{ $order->payments->count() }})</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Date</th><th>Method</th><th>Account</th><th>Note</th><th>Doc</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse($order->payments as $i => $p)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $p->payment_date?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $p->method ?: '—' }}</td>
                            <td>{{ $p->paymentAccount->name ?? '—' }}</td>
                            <td>{{ $p->note ?: '—' }}</td>
                            <td>@if($p->attachment)<a href="{{ asset('upload/payments/'.$p->attachment) }}" target="_blank"><i class="ri-attachment-line"></i></a>@else—@endif</td>
                            <td class="text-end">৳ {{ number_format($p->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-muted text-center py-3">No payments recorded</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-semibold">
                            <td colspan="6">Received / Due</td>
                            <td class="text-end">৳ {{ number_format($order->received_amount, 2) }} / ৳ {{ number_format($order->due_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
