@extends('admin.admin_master')
@section('admin')

@php
    $taka = fn ($value) => '৳ '.number_format((float) $value, 2);
@endphp

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Exchange Rates</h4>
                <small class="text-muted">The dollar rate for each day — used wherever an amount is in US dollars</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item">Rates &amp; Taxes</li>
                    <li class="breadcrumb-item active">Exchange Rates</li>
                </ol>
            </div>
        </div>

        <div class="row">
            {{-- ===================== Today ===================== --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Today · {{ \Illuminate\Support\Carbon::parse($today)->format('d M Y') }}</h5></div>
                    <div class="card-body">
                        @if($current)
                            <div class="fs-3 fw-semibold">$1 = {{ $taka($current->usd_rate) }}</div>
                            @if($current->isFor($today))
                                <div class="text-muted small">Set for today.</div>
                            @else
                                <div class="text-warning small">
                                    No rate set for today — the rate of {{ $current->rate_date->format('d M Y') }} is used until you set one.
                                </div>
                            @endif
                        @else
                            <div class="text-danger">No dollar rate set yet.</div>
                            <div class="text-muted small">Set today's rate to start converting dollar amounts.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===================== Set a rate ===================== --}}
            @can('exchange-rates.create')
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Set a Day's Rate</h5></div>
                    <div class="card-body">
                        <form action="{{ route('exchange.rate.store') }}" method="POST" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="rate_date" value="{{ old('rate_date', $today) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">$1 =</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" step="0.0001" min="0.0001" class="form-control" name="usd_rate" value="{{ old('usd_rate') }}" placeholder="122.50" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Save Rate</button>
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control form-control-sm" name="note" value="{{ old('note') }}" placeholder="Note (optional) — e.g. Bangladesh Bank rate">
                            </div>
                        </form>
                        <div class="text-muted small mt-2">A date that already has a rate gets the new one in its place.</div>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        {{-- ===================== History ===================== --}}
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Rate History <span class="text-muted fs-6 fw-normal">({{ number_format($rates->total()) }} days)</span></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.86rem">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Date</th>
                                <th class="text-end">$1 =</th>
                                <th class="text-end">Change</th>
                                <th>Note</th>
                                <th>Set By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rates as $rate)
                            @php
                                $previous = $rates->get($loop->index + 1) ?? $older;
                                $change = $previous ? round((float) $rate->usd_rate - (float) $previous->usd_rate, 4) : null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                                        <ul class="dropdown-menu">
                                            @can('exchange-rates.edit')
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-row="{{ json_encode(['id' => $rate->id, 'rate_date' => $rate->rate_date->toDateString(), 'usd_rate' => (float) $rate->usd_rate, 'note' => $rate->note]) }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            @endcan
                                            <x-history-link :record="$rate" />
                                            @can('exchange-rates.delete')
                                            <li>
                                                <form action="{{ route('exchange.rate.delete', $rate->id) }}" method="POST" class="m-0">
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
                                    @if($current && $current->id === $rate->id)
                                        <span class="badge bg-success-subtle text-success ms-1">In use</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $taka($rate->usd_rate) }}</td>
                                <td class="text-end">
                                    @if($change === null || $change == 0)
                                        <span class="text-muted">—</span>
                                    @else
                                        <span class="{{ $change > 0 ? 'text-danger' : 'text-success' }}">
                                            <i class="{{ $change > 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>
                                            {{ rtrim(rtrim(number_format(abs($change), 4), '0'), '.') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="small">{{ $rate->note ?: '—' }}</td>
                                <td class="small">{{ $rate->addedBy ? trim($rate->addedBy->first_name.' '.$rate->addedBy->last_name) : '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No rates yet — set today's above.</td>
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

@can('exchange-rates.edit')
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" action="{{ url('exchange-rates') }}" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Exchange Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="rate_date" data-field="rate_date" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">$1 =</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" step="0.0001" min="0.0001" class="form-control" name="usd_rate" data-field="usd_rate" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <input type="text" class="form-control" name="note" data-field="note">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('editModal')?.addEventListener('show.bs.modal', function (e) {
        const row = JSON.parse(e.relatedTarget.dataset.row);
        const form = document.getElementById('editForm');
        form.action = '{{ url('exchange-rates') }}/' + row.id;
        form.querySelectorAll('[data-field]').forEach(field => { field.value = row[field.dataset.field] ?? ''; });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this rate?', text: 'Days that used it fall back to the rate before it.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>

@endsection
