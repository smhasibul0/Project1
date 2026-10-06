@extends('admin.admin_master')
@section('admin')

@php
    $usdRate = (float) ($dollarRate?->usd_rate ?? 0);
    $usd = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
    $common = $rate->mostCommonAssessedPrice();
@endphp

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">
                    Rate — {{ $rate->hs_code }}
                    @if($isCurrent)<span class="badge bg-success-subtle text-success fs-6 align-middle ms-1">In use</span>@endif
                </h4>
                <small class="text-muted">{{ $rate->hsCode->description ?? $rate->description }}</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('rates.index') }}">Rates</a></li>
                    <li class="breadcrumb-item active">{{ $rate->hs_code }}</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted d-block">Rate date</small><span class="fw-semibold">{{ $rate->rate_date->format('d M Y') }}</span></div>
                    <div class="col-md-3"><small class="text-muted d-block">Reference (highest assessed)</small><span class="fw-semibold">{{ $usd($rate->unit_price) }} USD/kg</span>
                        @if($usdRate > 0)<span class="text-muted"> · ৳ {{ number_format((float) $rate->unit_price * $usdRate, 2) }}/kg</span>@endif</div>
                    <div class="col-md-3"><small class="text-muted d-block">Most common assessed</small>
                        {{ $common ? $usd($common['price']).' USD/kg on '.$common['count'].' of '.$rate->bills_count.' bills' : '—' }}</div>
                    <div class="col-md-3"><small class="text-muted d-block">Bills dated</small>
                        {{ $rate->period_from ? $rate->period_from->format('d M Y').' – '.$rate->period_to->format('d M Y') : '—' }}</div>
                    <div class="col-md-3"><small class="text-muted d-block">File</small>
                        @if($rate->file_path)<a href="{{ route('rates.pdf', $rate->id) }}" target="_blank"><i class="ri-file-pdf-2-line"></i> {{ $rate->original_name }}</a>@else — @endif</div>
                    <div class="col-md-3"><small class="text-muted d-block">Uploaded by</small>{{ $rate->addedBy ? trim($rate->addedBy->first_name.' '.$rate->addedBy->last_name) : '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Bills of Entry <span class="text-muted fs-6 fw-normal">({{ $rate->bills_count }})</span></h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">BE No</th>
                                <th>BE Date</th>
                                <th>Description</th>
                                <th>Cur.</th>
                                <th class="text-end">Declared / kg</th>
                                <th class="text-end">Assessed / kg</th>
                                <th class="text-end">Net Wt (kg)</th>
                                <th>Origin</th>
                                <th>Exporter</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rate->bills ?? [] as $bill)
                            <tr class="{{ (float) $bill['assessed_unit_price'] === (float) $rate->unit_price ? 'table-warning' : '' }}">
                                <td class="ps-3">{{ $bill['be_no'] ?? '—' }}</td>
                                <td class="text-nowrap">{{ $bill['be_date'] ? \Illuminate\Support\Carbon::parse($bill['be_date'])->format('d M Y') : '—' }}</td>
                                <td>{{ $bill['description'] }}</td>
                                <td>{{ $bill['currency'] }}</td>
                                <td class="text-end">{{ number_format((float) $bill['declared_unit_price'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $bill['assessed_unit_price'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $bill['net_weight'], 2) }}</td>
                                <td>{{ $bill['country'] }}</td>
                                <td class="small text-muted">{{ $bill['exporter'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">The highlighted bill set the reference price.</div>
        </div>

        <x-activity-history :record="$rate" />
    </div>
</div>

@endsection
