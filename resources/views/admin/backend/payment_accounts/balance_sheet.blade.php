@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        {{-- Page Header --}}
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Balance Sheet</h4>
            </div>
        </div>

        {{-- Filters Card --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3" style="color:#0ea5e9;">
                    <i class="ri-filter-line me-1"></i> Filters
                </h6>
                <form method="GET" action="{{ route('balance.sheet') }}" class="row g-3 align-items-end">

                    {{-- Business Location --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Business Location:</label>
                        <select class="form-select" name="location_id">
                            <option value="all">All locations</option>
                            @foreach($locations ?? [] as $loc)
                                <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                                    {{ $loc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter by date --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Filter by date:</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                            <input type="text" class="form-control" name="filter_date" id="bsDatePicker"
                                   value="{{ request('filter_date', \Carbon\Carbon::now()->format('d-m-Y')) }}"
                                   readonly style="background:#f8fafc;">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> Filter
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- Balance Sheet Table --}}
        <div class="card mb-3">
            <div class="card-body p-0">
                <style>
                    .bs-table { width:100%; border-collapse:collapse; font-size:.875rem; }
                    .bs-table .bs-header { background:#e2e8f0; color:#1e293b; font-weight:700; font-size:.8rem; text-transform:uppercase; padding:.65rem 1rem; }
                    .bs-table td { padding:.55rem 1rem; border-bottom:1px solid #f1f5f9; vertical-align:top; }
                    .bs-table tr:last-child td { border-bottom:none; }
                    .bs-table .bs-section-title { font-weight:700; padding:.6rem 1rem; background:#f8fafc; font-size:.78rem; color:#475569; }
                    .bs-table .bs-total-row td { background:#e2e8f0; font-weight:700; color:#1e293b; padding:.65rem 1rem; border-top:2px solid #cbd5e1; }
                    .bs-divider { width:1px; background:#e2e8f0; }
                    .bs-amount { text-align:right; white-space:nowrap; }
                    .bs-indent { padding-left:2rem !important; }
                </style>

                <table class="bs-table">
                    <thead>
                        <tr>
                            <td class="bs-header" style="width:50%; border-right:2px solid #cbd5e1;">Liability</td>
                            <td class="bs-header" style="width:50%;">Assets</td>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Main rows: liability left, assets right --}}
                        <tr>
                            {{-- LIABILITIES COLUMN --}}
                            <td style="border-right:2px solid #e2e8f0; vertical-align:top; padding:0;">
                                <table style="width:100%; border-collapse:collapse; font-size:.875rem;">
                                    <tr>
                                        <td style="padding:.6rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            <strong>Supplier Due:</strong>
                                        </td>
                                        <td class="bs-amount" style="padding:.6rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            ৳ {{ number_format($supplierDue ?? 0, 2) }}
                                        </td>
                                    </tr>
                                    {{-- Add more liability rows here as needed --}}
                                    @foreach($liabilities ?? [] as $liability)
                                    <tr>
                                        <td style="padding:.55rem 1rem; border-bottom:1px solid #f1f5f9;">{{ $liability['label'] }}</td>
                                        <td class="bs-amount" style="padding:.55rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            ৳ {{ number_format($liability['amount'], 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                            </td>

                            {{-- ASSETS COLUMN --}}
                            <td style="vertical-align:top; padding:0;">
                                <table style="width:100%; border-collapse:collapse; font-size:.875rem;">
                                    <tr>
                                        <td style="padding:.6rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            <strong>Customer Due:</strong>
                                        </td>
                                        <td class="bs-amount" style="padding:.6rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            ৳ {{ number_format($customerDue ?? 0, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:.55rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            <strong>Closing stock:</strong>
                                        </td>
                                        <td class="bs-amount" style="padding:.55rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            ৳ {{ number_format($closingStock ?? 0, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:.55rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            <strong>Account Balances:</strong>
                                        </td>
                                    </tr>
                                    @foreach($accountBalances ?? [] as $acc)
                                    <tr>
                                        <td style="padding:.45rem 1rem .45rem 2rem; border-bottom:1px solid #f1f5f9; color:#475569;">
                                            {{ $acc['name'] }}:
                                        </td>
                                        <td class="bs-amount" style="padding:.45rem 1rem; border-bottom:1px solid #f1f5f9;">
                                            ৳ {{ number_format($acc['balance'], 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bs-total-row">
                            <td style="border-right:2px solid #cbd5e1;">
                                <div class="d-flex justify-content-between">
                                    <span>Total Liability:</span>
                                    <span>৳ {{ number_format($totalLiability ?? 0, 2) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between">
                                    <span>Total Assets:</span>
                                    <span>৳ {{ number_format($totalAssets ?? 0, 2) }}</span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Print Button --}}
        <div class="d-flex justify-content-end mt-2 mb-4">
            <button class="btn btn-primary px-4" onclick="window.print()">
                <i class="ri-printer-line me-1"></i> Print
            </button>
        </div>

    </div>
</div>

@endsection