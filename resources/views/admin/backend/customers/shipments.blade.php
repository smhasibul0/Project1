@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'pending' => 'secondary', 'sourcing' => 'secondary', 'at_china_warehouse' => 'info',
        'shipped' => 'primary', 'at_port' => 'warning', 'at_bd_warehouse' => 'info',
        'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger',
    ];
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Shipments — {{ $customer->business_name ?: $customer->name }}</h4>
                <small class="text-muted">
                    {{ $customer->contact_code }}
                    @if($customer->shipping_mark) · Shipping mark <strong>{{ $customer->shipping_mark }}</strong>@endif
                    @if($customer->mobile) · {{ $customer->mobile }}@endif
                </small>
            </div>
            <div class="text-end">
                <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Customers</a>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach([
                ['Shipments', number_format($summary['shipments']), 'body', 'ri-ship-line'],
                ['On the way', number_format($summary['on_the_way']), 'primary', 'ri-truck-line'],
                ['Delivered', number_format($summary['delivered']), 'success', 'ri-checkbox-circle-line'],
                ['Total CBM', $qty($summary['cbm']), 'body', 'ri-box-3-line'],
                ['Billed', '৳ '.number_format($summary['billed'], 2), 'body', 'ri-file-list-3-line'],
                ['Due', '৳ '.number_format($summary['due'], 2), $summary['due'] > 0 ? 'danger' : 'success', 'ri-error-warning-line'],
            ] as [$label, $value, $tone, $icon])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3">
                        <small class="text-muted d-block"><i class="{{ $icon }} me-1"></i>{{ $label }}</small>
                        <h5 class="mb-0 mt-1 fs-17 text-{{ $tone }}">{{ $value }}</h5>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body p-0">
                <x-data-table id="shipmentsTable" :export-name="'shipments-'.Str::slug($customer->contact_code ?: $customer->name)">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Shipment No</th>
                                <th>Shipping Mark</th>
                                <th>Container</th>
                                <th class="text-end">Cartons</th>
                                <th class="text-end">CBM</th>
                                <th data-filter="Status">Status</th>
                                <th>ETA / Delivered</th>
                                <th class="text-end">Billed</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="{{ route('order.show', $order->id) }}"><i class="ri-eye-line me-2"></i>View Order</a></li>
                                            <li><a class="dropdown-item" href="{{ route('order.track', $order->track_token) }}" target="_blank"><i class="ri-map-pin-time-line me-2"></i>Tracking</a></li>
                                            @can('orders.invoice')
                                            <li><a class="dropdown-item" href="{{ route('order.invoice.pdf', $order->id) }}"><i class="ri-file-pdf-2-line me-2"></i>Invoice PDF</a></li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td><a href="{{ route('order.show', $order->id) }}" class="badge bg-light text-dark">{{ $order->order_no }}</a></td>
                                <td class="text-nowrap">{{ $order->order_date?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $order->shipment_no ?: '—' }}</td>
                                <td>{{ $order->shipping_mark ?: '—' }}</td>
                                <td>{{ $order->containerNumbers() ?: '—' }}</td>
                                <td class="text-end">{{ $qty($order->items->sum('package_quantity')) }}</td>
                                <td class="text-end">{{ $qty($order->total_cbm) }}</td>
                                <td><span class="badge bg-{{ $statusColors[$order->goods_status] ?? 'secondary' }}">{{ $order->statusLabel() }}</span></td>
                                <td class="text-nowrap">
                                    @if($order->delivered_date)
                                        <span class="text-success">{{ $order->delivered_date->format('d M Y') }}</span>
                                    @elseif($order->tentative_receive_date)
                                        {{ $order->tentative_receive_date->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">৳ {{ number_format($order->total_amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($order->received_amount, 2) }}</td>
                                <td class="text-end {{ (float) $order->due_amount > 0 ? 'text-danger fw-semibold' : '' }}">৳ {{ number_format($order->due_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
                @if($orders->isEmpty())
                <p class="text-center text-muted py-4 mb-0">No shipments for this customer yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
