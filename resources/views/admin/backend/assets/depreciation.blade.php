@extends('admin.admin_master')
@section('admin')

@php
    $prevMonth = $month->copy()->subMonth()->format('Y-m');
    $nextMonth = $month->copy()->addMonth()->format('Y-m');
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Depreciation</h4>
                <small class="text-muted">Post one month at a time across the register. Every month is a row you can reverse.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('asset.depreciation', ['month' => $prevMonth]) }}" class="btn btn-sm btn-outline-secondary" title="Previous month"><i class="ri-arrow-left-s-line"></i></a>
                <form method="GET" action="{{ route('asset.depreciation') }}" class="m-0">
                    <input type="month" class="form-control form-control-sm" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                </form>
                <a href="{{ route('asset.depreciation', ['month' => $nextMonth]) }}" class="btn btn-sm btn-outline-secondary" title="Next month"><i class="ri-arrow-right-s-line"></i></a>
                <a href="{{ route('assets.index') }}" class="btn btn-outline-primary ms-2"><i class="ri-archive-2-line me-1"></i> Register</a>
                @if($pending->isNotEmpty() && auth()->user()->can('assets.depreciation.generate'))
                <form action="{{ route('asset.depreciation.generate') }}" method="POST" class="m-0" id="postForm">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <button type="button" class="btn btn-primary rounded-pill px-4" id="postBtn">
                        <i class="ri-check-double-line me-1"></i> Post {{ $month->format('M Y') }}
                        <span class="badge bg-white text-primary ms-1">{{ $pending->count() }}</span>
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Month summary --}}
        <div class="row g-3 mb-3">
            @foreach([
                ['Posted this month', $postedTotal, 'primary', 'ri-check-double-line'],
                ['Waiting to post', $pendingTotal, 'warning', 'ri-time-line'],
                ['Year to date', $yearToDate, 'info', 'ri-calendar-line'],
                ['Net book value', $bookValue, 'success', 'ri-wallet-3-line'],
            ] as [$label, $value, $tone, $icon])
            <div class="col-6 col-lg-3">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3">
                        <small class="text-muted d-block"><i class="{{ $icon }} me-1 text-{{ $tone }}"></i>{{ $label }}</small>
                        <h5 class="mb-0 mt-1 fs-17 text-{{ $tone }}">৳ {{ number_format($value, 2) }}</h5>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($pending->isNotEmpty())
        <div class="alert alert-primary d-flex align-items-center gap-2 py-2">
            <i class="ri-information-line fs-18"></i>
            <div class="small">
                {{ $pending->count() }} asset(s) have no {{ $month->format('F Y') }} charge yet —
                ৳ {{ number_format($pendingTotal, 2) }} in total.
                Use <strong>Post {{ $month->format('M Y') }}</strong> to charge them.
            </div>
        </div>
        @endif

        @if($fullyDepreciated > 0)
        <div class="alert alert-light border d-flex align-items-center gap-2 py-2">
            <i class="ri-checkbox-circle-line fs-18 text-success"></i>
            <div class="small">{{ $fullyDepreciated }} asset(s) are fully depreciated and no longer take a charge.</div>
        </div>
        @endif

        @if($pending->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-15">Waiting to post — {{ $month->format('F Y') }}</h5>
                <small class="text-muted">{{ $pending->count() }} asset(s)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th><th>Asset</th><th>Method</th>
                                <th class="text-end">Book value now</th>
                                <th class="text-end">This month</th>
                                <th class="text-end">Book value after</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pending as $asset)
                            @php $charge = $asset->monthlyCharge($period); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $asset->asset_code }}</td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->methodLabel() }}</td>
                                <td class="text-end">৳ {{ number_format($asset->bookValue(), 2) }}</td>
                                <td class="text-end text-warning fw-semibold">৳ {{ number_format($charge, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($asset->bookValue() - $charge, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-semibold">
                                <td colspan="4" class="text-end">Total</td>
                                <td class="text-end">৳ {{ number_format($pendingTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-15">Posted — {{ $month->format('F Y') }}</h5>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">{{ $posted->count() }} entr(ies)</small>
                    @if($posted->isNotEmpty() && auth()->user()->can('assets.depreciation.delete'))
                    <form action="{{ route('asset.depreciation.month.delete') }}" method="POST" class="m-0" id="reverseMonthForm">
                        @csrf @method('DELETE')
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="reverseMonthBtn">
                            <i class="ri-arrow-go-back-line me-1"></i> Reverse month
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <x-data-table id="depreciationTable" export-name="depreciation">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Code</th>
                                <th>Asset</th>
                                <th data-filter="Category">Category</th>
                                <th data-filter="Method">Method</th>
                                <th class="text-end">Charge</th>
                                <th class="text-end">Book Value After</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($posted as $entry)
                            <tr>
                                <td>
                                    @can('assets.depreciation.delete')
                                    <form action="{{ route('asset.depreciation.delete', $entry->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger reverse-btn" title="Reverse this entry">
                                            <i class="ri-arrow-go-back-line"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                                <td class="fw-semibold">{{ $entry->asset->asset_code ?? '—' }}</td>
                                <td>{{ $entry->asset->name ?? '—' }}</td>
                                <td>{{ $entry->asset->category->name ?? '—' }}</td>
                                <td>{{ $entry->asset?->methodLabel() ?? '—' }}</td>
                                <td class="text-end fw-semibold">৳ {{ number_format($entry->amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($entry->book_value_after, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const postBtn = document.getElementById('postBtn');
    if (postBtn) {
        postBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Post {{ $month->format("F Y") }}?',
                html: 'This charges <strong>{{ $pending->count() }}</strong> asset(s) a total of <strong>৳ {{ number_format($pendingTotal, 2) }}</strong>.<br>Assets already posted for the month are skipped.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, post',
            }).then(r => { if (r.isConfirmed) document.getElementById('postForm').submit(); });
        });
    }

    const reverseMonthBtn = document.getElementById('reverseMonthBtn');
    if (reverseMonthBtn) {
        reverseMonthBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Reverse {{ $month->format("F Y") }}?',
                text: 'Every entry posted for this month is deleted and the book values go back up.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, reverse',
                confirmButtonColor: '#ef4444',
            }).then(r => { if (r.isConfirmed) document.getElementById('reverseMonthForm').submit(); });
        });
    }

    document.querySelectorAll('.reverse-btn').forEach(btn => btn.addEventListener('click', function () {
        Swal.fire({
            title: 'Reverse this entry?',
            text: 'The charge is removed and the asset goes back to its previous book value.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, reverse',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
