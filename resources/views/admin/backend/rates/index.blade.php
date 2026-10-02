@extends('admin.admin_master')
@section('admin')

@php
    $usdRate = (float) $company->usd_rate;
    $usd = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
@endphp

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Rates</h4>
                <small class="text-muted">Customs valuation reports — the reference declared value for each HS code</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item">Rates &amp; Taxes</li>
                    <li class="breadcrumb-item active">Rates</li>
                </ol>
            </div>
        </div>

        <div class="row">
            {{-- ===================== Dollar rate ===================== --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Dollar Rate</h5></div>
                    <div class="card-body">
                        <div class="fs-4 fw-semibold mb-1">
                            @if($usdRate > 0)
                                1 USD = ৳ {{ number_format($usdRate, 2) }}
                            @else
                                <span class="text-danger fs-6">Not set yet</span>
                            @endif
                        </div>
                        <div class="text-muted small mb-3">
                            @if($company->usd_rate_date)
                                Set on {{ $company->usd_rate_date->format('d M Y') }}.
                            @endif
                            Declared value = price per kg × net weight × this rate.
                        </div>
                        @can('rates.dollar')
                        <form action="{{ route('rates.dollar') }}" method="POST" class="d-flex gap-2">
                            @csrf
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">1 USD = ৳</span>
                                <input type="number" step="0.0001" min="0.0001" class="form-control" name="usd_rate" value="{{ $usdRate > 0 ? $usd($usdRate) : '' }}" required>
                            </div>
                            <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- ===================== Upload ===================== --}}
            @can('rates.upload')
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Upload Valuation Report</h5></div>
                    <div class="card-body">
                        <form action="{{ route('rates.store') }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Rate date</label>
                                <input type="date" class="form-control" name="rate_date" value="{{ old('rate_date', now()->timezone(config('app.display_timezone'))->toDateString()) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Report PDF(s)</label>
                                <input type="file" class="form-control" name="reports[]" accept="application/pdf,.pdf" multiple required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100"><i class="ri-upload-2-line me-1"></i> Upload</button>
                            </div>
                        </form>
                        <div class="alert alert-light border mt-3 mb-0 small">
                            Upload the <em>ValuationReport_Analysis</em> PDF Customs prints for an HS code. Every bill of entry in it is read,
                            and the <strong>highest assessed unit price (USD/kg)</strong> becomes that code's reference.
                            Quotations use the newest dated rate for each code; older ones stay here as history.
                        </div>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        {{-- ===================== Rates ===================== --}}
        <div class="card mt-3">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Uploaded Rates <span class="text-muted fs-6 fw-normal">({{ number_format($rates->total()) }})</span></h5>
                <form action="{{ route('rates.index') }}" method="GET" class="d-flex gap-2">
                    <input type="search" class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="HS code or description" style="min-width:240px">
                    <button class="btn btn-sm btn-outline-primary">Search</button>
                    @if($search !== '')
                        <a href="{{ route('rates.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.84rem">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Rate Date</th>
                                <th>HS Code</th>
                                <th class="text-end">Highest Assessed</th>
                                <th class="text-end">In Taka</th>
                                <th class="text-end">Most Common</th>
                                <th class="text-end">Bills</th>
                                <th>Bills Dated</th>
                                <th>Uploaded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rates as $rate)
                            @php($common = $rate->mostCommonAssessedPrice())
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="{{ route('rates.show', $rate->id) }}"><i class="ri-list-check-2 me-1"></i> View Bills</a></li>
                                            @if($rate->file_path)
                                            <li><a class="dropdown-item" href="{{ route('rates.pdf', $rate->id) }}" target="_blank"><i class="ri-file-pdf-2-line me-1"></i> Open PDF</a></li>
                                            @endif
                                            <x-history-link :record="$rate" />
                                            @can('rates.delete')
                                            <li>
                                                <form action="{{ route('rates.delete', $rate->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn"><i class="ri-delete-bin-line me-1"></i> Delete</button>
                                                </form>
                                            </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    {{ $rate->rate_date->format('d M Y') }}
                                    @if(in_array($rate->id, $currentIds, true))
                                        <span class="badge bg-success-subtle text-success ms-1">In use</span>
                                    @endif
                                </td>
                                <td style="min-width:280px">
                                    <div class="fw-semibold text-nowrap">{{ $rate->hs_code }}</div>
                                    <div class="text-muted small">{{ Str::limit($rate->hsCode->description ?? $rate->description, 70) }}</div>
                                </td>
                                <td class="text-end fw-semibold text-nowrap">{{ $usd($rate->unit_price) }} USD/kg</td>
                                <td class="text-end text-nowrap">{{ $usdRate > 0 ? '৳ '.number_format((float) $rate->unit_price * $usdRate, 2).'/kg' : '—' }}</td>
                                <td class="text-end text-nowrap text-muted">
                                    @if($common)
                                        {{ $usd($common['price']) }} <small>({{ $common['count'] }} of {{ $rate->bills_count }})</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">{{ $rate->bills_count }}</td>
                                <td class="text-nowrap small">
                                    @if($rate->period_from)
                                        {{ $rate->period_from->format('d M Y') }} – {{ $rate->period_to->format('d M Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small">{{ $rate->addedBy ? trim($rate->addedBy->first_name.' '.$rate->addedBy->last_name) : '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    @if($search !== '')
                                        Nothing matches “{{ $search }}”.
                                    @else
                                        No rates yet — upload a Customs valuation report above.
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($rates->hasPages())
                <div class="card-footer">{{ $rates->links() }}</div>
            @endif
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this rate?', text: 'Quotations fall back to the next newest rate for this HS code.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>

@endsection
