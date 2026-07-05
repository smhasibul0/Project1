@extends('admin.admin_master')
@section('admin')

@php
    $statusColors = [
        'draft' => 'secondary', 'opened' => 'info', 'released' => 'success',
        'settled' => 'primary', 'cancelled' => 'danger',
    ];
@endphp

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Letters of Credit</h4>
                <small class="text-muted">LC register &amp; bank settlement tracking</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">LC</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">LC List</h5>
                <a href="{{ route('lc.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add LC
                </a>
            </div>
            <div class="card-body p-0">
                <x-data-table id="lcTable" export-name="letters-of-credit">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>PI Date</th>
                                <th>PI No</th>
                                <th>LC Number</th>
                                <th>Order</th>
                                <th>Opening Bank</th>
                                <th>Container No</th>
                                <th>Commodity</th>
                                <th class="text-end">Inv. Amount</th>
                                <th>Currency</th>
                                <th class="text-end">Net Received</th>
                                <th class="text-end">Bank Charges</th>
                                <th class="text-end">USD Sell</th>
                                <th>USD Sell Date</th>
                                <th data-filter="Status">LC Status</th>
                                <th>Released Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lcs as $lc)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="{{ route('lc.show', $lc->id) }}"><i class="ri-eye-line me-2"></i>View</a></li>
                                            <li><a class="dropdown-item" href="{{ route('lc.edit', $lc->id) }}"><i class="ri-edit-line me-2"></i>Edit</a></li>
                                            @if($lc->pi_document)
                                            <li><a class="dropdown-item" href="{{ asset('upload/lc/'.$lc->pi_document) }}" target="_blank"><i class="ri-attachment-line me-2"></i>PI Document</a></li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('lc.delete', $lc->id) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn"><i class="ri-delete-bin-line me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td>{{ $lc->pi_date?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    @if($lc->pi_document)
                                        <a href="{{ asset('upload/lc/'.$lc->pi_document) }}" target="_blank">{{ $lc->pi_no ?: $lc->lc_code }}</a>
                                    @else
                                        {{ $lc->pi_no ?: '—' }}
                                    @endif
                                </td>
                                <td>{{ $lc->lc_number ?: '—' }}</td>
                                <td><a href="{{ route('order.show', $lc->order_id) }}"><span class="badge bg-light text-dark">{{ $lc->order->order_no ?? '—' }}</span></a></td>
                                <td>{{ $lc->opening_bank ?: '—' }}</td>
                                <td>{{ $lc->container_no ?: '—' }}</td>
                                <td>{{ $lc->commodity ?: '—' }}</td>
                                <td class="text-end">{{ number_format($lc->invoice_amount, 2) }}</td>
                                <td>{{ $lc->currency }}</td>
                                <td class="text-end">{{ $lc->net_amount_received !== null ? number_format($lc->net_amount_received, 2) : '—' }}</td>
                                <td class="text-end">{{ number_format($lc->bank_charges, 2) }}</td>
                                <td class="text-end">{{ $lc->usd_sell_rate !== null ? rtrim(rtrim(number_format($lc->usd_sell_rate, 4), '0'), '.') : '—' }}</td>
                                <td>{{ $lc->usd_sell_date?->format('d M Y') ?: '—' }}</td>
                                <td><span class="badge bg-{{ $statusColors[$lc->lc_status] ?? 'secondary' }}">{{ $lc->statusLabel() }}</span></td>
                                <td>{{ $lc->released_date?->format('d M Y') ?: '—' }}</td>
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
    // Fixed Popper strategy so the responsive-table overflow doesn't clip the open menu.
    document.querySelectorAll('#lcTable [data-bs-toggle="dropdown"]').forEach(function (el) {
        new bootstrap.Dropdown(el, {
            popperConfig: function (defaultConfig) {
                return Object.assign({}, defaultConfig, { strategy: 'fixed' });
            },
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this LC?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
