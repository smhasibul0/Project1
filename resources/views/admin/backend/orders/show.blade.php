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
                @can('orders.label')<a href="{{ route('order.label', $order->id) }}" target="_blank" class="btn btn-dark btn-sm"><i class="ri-qr-code-line me-1"></i> QR Labels</a>@endcan
                @can('orders.invoice')<a href="{{ route('order.invoice', $order->id) }}" target="_blank" class="btn btn-success btn-sm"><i class="ri-file-text-line me-1"></i> Invoice</a>@endcan
                @can('orders.edit')<a href="{{ route('order.edit', $order->id) }}" class="btn btn-primary btn-sm"><i class="ri-edit-line me-1"></i> Edit</a>@endcan
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
                <div class="col-md-3">
                    <small class="text-muted d-block">Container No</small>
                    <strong>{{ $order->containerNumbers() ?: '—' }}</strong>
                    @if($order->containers->isNotEmpty())
                        <small class="text-muted d-block">from the container it's loaded in</small>
                    @endif
                </div>
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
                            <select class="form-control mb-2" name="goods_status" id="goodsStatusSelect" required>
                                @foreach(\App\Models\Order::goodsStatuses() as $key => $label)
                                    <option value="{{ $key }}" @selected($order->goods_status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>

                            {{-- Appears when marking "At BD Warehouse": pick where the goods land. --}}
                            <div id="warehousePicker" style="display:none;">
                                <label class="form-label">Receive into Warehouse</label>
                                <select class="form-control mb-2" name="warehouse_id" id="warehouseSelect">
                                    <option value="">-- Select Warehouse --</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" @selected($order->warehouse_id == $w->id)>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @if($order->warehouseStocks()->exists())
                                    <div class="text-success small mb-2"><i class="ri-checkbox-circle-line me-1"></i>Goods already received into inventory.</div>
                                @else
                                    <div class="text-muted small mb-2">Order items will be added to this warehouse's inventory.</div>
                                @endif
                            </div>

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
                        <div class="d-flex gap-1">
                            <a href="{{ route('order.track', $order->track_token) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Open the customer's tracking page">
                                <i class="ri-external-link-line me-1"></i> Public link
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-track-link"
                                    data-link="{{ route('order.track', $order->track_token) }}" title="Copy the link to send to the customer">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- The carton QR: what the customer sticks on every package. --}}
                        @php
                            $trackUrl = route('order.track', $order->track_token);
                            $orderQr = preg_replace('/^<\?xml[^>]*\?>\s*/', '', (string) QrCode::format('svg')
                                ->size(300)->margin(1)->errorCorrection('M')->generate($trackUrl));
                        @endphp
                        <div class="d-flex gap-3 align-items-center border rounded p-3 mb-3 flex-wrap">
                            <div style="width:120px; flex:none;">{!! $orderQr !!}</div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-5">{{ $order->shipping_mark ?: $order->order_no }}</div>
                                <div class="text-muted small mb-2">
                                    Scanning this opens the tracking page. Staff who are signed in can count
                                    cartons through each stage from it.
                                </div>
                                @can('orders.label')
                                <a href="{{ route('order.label', $order->id) }}" target="_blank" class="btn btn-sm btn-dark">
                                    <i class="ri-printer-line me-1"></i> Print carton labels
                                </a>
                                @endcan
                                <span class="text-muted small ms-1">
                                    {{ rtrim(rtrim(number_format($order->totalCartons(), 2), '0'), '.') ?: '0' }} carton(s)
                                </span>
                            </div>
                        </div>

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
                                <th>#</th><th>HS Code</th><th>Item</th><th class="text-end">Qty</th>
                                <th class="text-end">Pkg</th><th class="text-end">Net Wt</th><th class="text-end">CBM</th>
                                <th class="text-end">Declared Value</th><th class="text-end">Duty</th><th class="text-end">Charge</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="fw-semibold text-nowrap">{{ $item->hs_code ?: '—' }}</td>
                                <td>{{ $item->item_description ?: ($item->hsCodeRecord->description ?? '—') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->package_quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ $item->net_weight ? rtrim(rtrim(number_format($item->net_weight, 3), '0'), '.') : '—' }}</td>
                                <td class="text-end">{{ $item->cbm ? rtrim(rtrim(number_format($item->cbm, 4), '0'), '.') : '—' }}</td>
                                <td class="text-end">৳ {{ number_format($item->declared_value, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->duty_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($item->line_total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="6" class="text-end">Totals ({{ number_format((float) $order->sell_rate_per_cbm, 2) }}/CBM):</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($order->total_cbm, 4), '0'), '.') }}</td>
                                <td class="text-end">৳ {{ number_format($order->items->sum('declared_value'), 2) }}</td>
                                <td class="text-end">৳ {{ number_format($order->duty_total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            @can('costs.view')
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="mb-0">Order Costs ({{ $order->costs->count() }})</h6>
                        @can('costs.create')
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal"><i class="ri-add-line me-1"></i> Add Cost</button>
                        @endcan
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr><th>Category</th><th>Title</th><th>Date</th><th>Account</th><th>Doc</th><th class="text-end">Amount</th>@can('costs.delete')<th></th>@endcan</tr>
                                </thead>
                                <tbody>
                                    @forelse($order->costs as $c)
                                    <tr>
                                        <td>{{ $c->category->name ?? '—' }}</td>
                                        <td>{{ $c->title }}</td>
                                        <td>{{ $c->cost_date?->format('d M Y') ?: '—' }}</td>
                                        <td>{{ $c->paymentAccount->name ?? '—' }}</td>
                                        <td>@if($c->attachment)<a href="{{ asset('upload/costs/'.$c->attachment) }}" target="_blank"><i class="ri-attachment-line"></i></a>@else—@endif</td>
                                        <td class="text-end">৳ {{ number_format($c->amount, 2) }}</td>
                                        @can('costs.delete')
                                        <td class="text-end">
                                            <form action="{{ route('order.cost.delete', [$order->id, $c->id]) }}" method="POST" class="m-0">
                                                @csrf @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 delete-cost-btn"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                        @endcan
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-muted text-center py-3">No costs recorded</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-semibold"><td colspan="5">Total Costs</td><td class="text-end">৳ {{ number_format($order->total_expense, 2) }}</td>@can('costs.delete')<td></td>@endcan</tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endcan
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Financials</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr><th>Subtotal</th><td class="text-end">৳ {{ number_format($order->subtotal, 2) }}</td></tr>
                            <tr><th>Discount</th><td class="text-end">{{ $order->discount_type === 'percentage' ? number_format($order->discount_value, 2).'%' : '৳ '.number_format($order->discount_value, 2) }}</td></tr>
                            <tr><th>Total Amount</th><td class="text-end fw-semibold">৳ {{ number_format($order->total_amount, 2) }}</td></tr>
                            <tr><th>Received ({{ ucfirst($order->payment_status) }})</th><td class="text-end">৳ {{ number_format($order->received_amount, 2) }}</td></tr>
                            <tr><th>Due</th><td class="text-end">৳ {{ number_format($order->due_amount, 2) }}</td></tr>
                            <tr><td colspan="2" class="text-muted small pt-2">Cost breakdown</td></tr>
                            <tr><th class="fw-normal ps-3">Freight ({{ number_format((float) $order->cost_rate_per_cbm, 2) }}/CBM)</th><td class="text-end">৳ {{ number_format($order->freight_cost, 2) }}</td></tr>
                            <tr><th class="fw-normal ps-3">Duty &amp; Taxes</th><td class="text-end">৳ {{ number_format($order->duty_total, 2) }}</td></tr>
                            <tr><th class="fw-normal ps-3">Order Costs</th><td class="text-end">৳ {{ number_format($order->total_expense, 2) }}</td></tr>
                            <tr><th class="fw-normal ps-3">LC Cost</th><td class="text-end">৳ {{ number_format($order->lc_cost, 2) }}</td></tr>
                            <tr><th class="fw-normal ps-3">Allocated Container Cost</th><td class="text-end">৳ {{ number_format($order->container_cost, 2) }}</td></tr>
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

        {{-- Containers --}}
        @if($order->containers->isNotEmpty())
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Containers ({{ $order->containers->count() }})</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Code</th><th>Shipment No</th><th>Container No</th>
                            <th class="text-end">CTN</th><th class="text-end">Weight</th><th class="text-end">CBM</th>
                            <th class="text-end">Container Cost</th><th class="text-end">This Order's Share</th>
                            <th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->containers as $ct)
                        <tr>
                            <td>{{ $ct->container_code }}</td>
                            <td>{{ $ct->shipment_no ?: '—' }}</td>
                            <td class="fw-semibold">{{ $ct->container_number ?: '—' }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($ct->pivot->ctn, 2), '0'), '.') }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($ct->pivot->weight, 2), '0'), '.') }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($ct->pivot->cbm, 4), '0'), '.') }}</td>
                            <td class="text-end">৳ {{ number_format($ct->costsTotal(), 2) }}</td>
                            <td class="text-end fw-semibold">
                                ৳ {{ number_format($order->shareOfContainerCost($ct), 2) }}
                                <small class="text-muted d-block">{{ \App\Models\Container::allocationBases()[$ct->allocation_basis] ?? 'By CBM (volume)' }}</small>
                            </td>
                            <td><span class="badge bg-secondary">{{ $ct->statusLabel() }}</span></td>
                            <td class="text-end">@can('containers.view')<a href="{{ route('container.show', $ct->id) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="ri-eye-line"></i></a>@endcan</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="7" class="text-end">Allocated to this order:</td>
                            <td class="text-end">৳ {{ number_format($order->container_cost, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- Letters of Credit --}}
        @can('lc.view')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Letters of Credit ({{ $order->lcs->count() }})</h6>
                @can('lc.create')<a href="{{ route('lc.create', ['order_id' => $order->id]) }}" class="btn btn-sm btn-primary"><i class="ri-add-line me-1"></i> Create LC</a>@endcan
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr><th>LC Code</th><th>PI No</th><th>LC Number</th><th>Opening Bank</th><th class="text-end">Inv. Amount</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($order->lcs as $lc)
                        <tr>
                            <td>{{ $lc->lc_code }}</td>
                            <td>{{ $lc->pi_no ?: '—' }}</td>
                            <td>{{ $lc->lc_number ?: '—' }}</td>
                            <td>{{ $lc->opening_bank ?: '—' }}</td>
                            <td class="text-end">{{ $lc->currency }} {{ number_format($lc->invoice_amount, 2) }}</td>
                            <td><span class="badge bg-secondary">{{ $lc->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('lc.show', $lc->id) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="ri-eye-line"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-muted text-center py-3">No letters of credit yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endcan
    </div>
</div>

{{-- ===================== Add Cost modal ===================== --}}
@can('costs.create')
<div class="modal fade" id="addCostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('order.cost.store', $order->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Cost — {{ $order->order_no }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="cost_category_id">
                            <option value="">-- Uncategorized --</option>
                            @foreach($costCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Sea freight, C&F charge">
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
                    <button type="submit" class="btn btn-primary px-4">Save Cost</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Copy the customer's tracking link to the clipboard.
    document.querySelectorAll('.copy-track-link').forEach(btn => btn.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(btn.dataset.link);
            toastr.success('Tracking link copied — send it to the customer.');
        } catch (e) {
            Swal.fire({ title: 'Tracking link', input: 'text', inputValue: btn.dataset.link, confirmButtonColor: '#6366f1' });
        }
    }));

    document.querySelectorAll('.delete-cost-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this cost?', text: 'Any linked account payment will be reversed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));

    // Reveal the warehouse picker only when marking goods "At BD Warehouse".
    const statusSel = document.getElementById('goodsStatusSelect');
    const picker = document.getElementById('warehousePicker');
    if (statusSel && picker) {
        const toggle = () => { picker.style.display = statusSel.value === 'at_bd_warehouse' ? '' : 'none'; };
        statusSel.addEventListener('change', toggle);
        toggle();
    }
});
</script>
@endcan
@endsection
