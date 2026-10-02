@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">
        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Asset Register</h4>
                <small class="text-muted">Everything the company owns, what it cost and what it is carried at today.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('asset.depreciation') }}" class="btn btn-outline-primary">
                    <i class="ri-line-chart-line me-1"></i> Depreciation
                </a>
                @if($categories->isEmpty())
                @can('asset-categories.view')
                <a href="{{ route('asset.categories') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Category First
                </a>
                @endcan
                @else
                @can('assets.create')
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#assetModal">
                    <i class="ri-add-line me-1"></i> Add Asset
                </button>
                @endcan
                @endif
            </div>
        </div>

        {{-- Register summary --}}
        <div class="row g-3 mb-3">
            @foreach([
                ['Assets on books', $onBooksCount, 'primary', 'ri-archive-2-line', false],
                ['Total cost', $totalCost, 'dark', 'ri-price-tag-3-line', true],
                ['Accumulated depreciation', $accumulated, 'warning', 'ri-arrow-down-circle-line', true],
                ['Net book value', $bookValue, 'success', 'ri-wallet-3-line', true],
                ['Disposed / written off', $disposedCount, 'secondary', 'ri-delete-bin-7-line', false],
            ] as [$label, $value, $tone, $icon, $isMoney])
            <div class="col-6 col-lg">
                <div class="card mb-0 h-100">
                    <div class="card-body py-3">
                        <small class="text-muted d-block"><i class="{{ $icon }} me-1 text-{{ $tone }}"></i>{{ $label }}</small>
                        <h5 class="mb-0 mt-1 fs-17 text-{{ $tone === 'dark' ? 'body' : $tone }}">
                            @if($isMoney)৳ {{ number_format($value, 2) }}@else{{ $value }}@endif
                        </h5>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($categories->isEmpty())
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2">
            <i class="ri-error-warning-line fs-18"></i>
            <div class="small">
                No asset categories yet. <a href="{{ route('asset.categories') }}" class="fw-semibold">Add a category</a>
                — Vehicles, IT Equipment, Furniture — and each one carries the depreciation basis its assets start from.
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-15">All Assets</h5>
                <small class="text-muted">{{ $assets->count() }} asset(s)</small>
            </div>
            <div class="card-body p-0">
                <x-data-table id="assetsTable" export-name="asset-register">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">Action</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th data-filter="Category">Category</th>
                                <th>Purchased</th>
                                <th class="text-end">Cost</th>
                                <th data-filter="Method">Method</th>
                                <th class="text-end">Depreciated</th>
                                <th class="text-end">Book Value</th>
                                <th data-filter="Status">Status</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assets as $asset)
                            <tr>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewAsset{{ $asset->id }}">
                                                    <i class="ri-eye-line me-1"></i> View
                                                </button>
                                            </li>
                                            @can('assets.edit')
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editAsset{{ $asset->id }}">
                                                    <i class="ri-edit-line me-1"></i> Edit
                                                </button>
                                            </li>
                                            @endcan
                                            @if($asset->status === 'in_use')
                                            @can('assets.dispose')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#disposeAsset{{ $asset->id }}">
                                                    <i class="ri-logout-box-r-line me-1"></i> Dispose / Write off
                                                </button>
                                            </li>
                                            @endcan
                                            @else
                                            @can('assets.restore')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('asset.restore', $asset->id) }}" method="POST" class="m-0">@csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="ri-arrow-go-back-line me-1"></i> Put back on books
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                            @endif
                                            @can('assets.delete')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('asset.delete', $asset->id) }}" method="POST" class="m-0">@csrf @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger delete-btn" data-months="{{ $asset->depreciations->count() }}">
                                                        <i class="ri-delete-bin-line me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                            @endcan
                                            <x-history-link :record="$asset" />
                                        </ul>
                                    </div>
                                </td>
                                <td class="fw-semibold">{{ $asset->asset_code }}</td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->category->name ?? '—' }}</td>
                                <td>{{ $asset->purchase_date?->format('d M Y') }}</td>
                                <td class="text-end fw-semibold">৳ {{ number_format($asset->purchase_cost, 2) }}</td>
                                <td>{{ $asset->methodLabel() }}</td>
                                <td class="text-end text-warning">৳ {{ number_format($asset->accumulatedDepreciation(), 2) }}</td>
                                <td class="text-end fw-semibold">৳ {{ number_format($asset->bookValue(), 2) }}</td>
                                <td>
                                    @if($asset->status === 'in_use')
                                        <span class="badge bg-success-subtle text-success">In use</span>
                                    @elseif($asset->status === 'disposed')
                                        <span class="badge bg-secondary-subtle text-secondary">Disposed</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Written off</span>
                                    @endif
                                </td>
                                <td>{{ $asset->location ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-data-table>
            </div>
        </div>
    </div>
</div>

{{-- Add Asset --}}
@can('assets.create')
<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('asset.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.backend.assets._fields', ['prefix' => 'add', 'asset' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endcan

{{-- Per-asset modals: view, edit, dispose --}}
@foreach($assets as $asset)

<div class="modal fade" id="viewAsset{{ $asset->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $asset->asset_code }} — {{ $asset->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><small class="text-muted d-block">Category</small><strong>{{ $asset->category->name ?? '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Purchased</small><strong>{{ $asset->purchase_date?->format('d M Y') }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Status</small><strong>{{ $asset->statusLabel() }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Cost</small><strong>৳ {{ number_format($asset->purchase_cost, 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Salvage value</small><strong>৳ {{ number_format($asset->salvage_value, 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Method</small><strong>{{ $asset->methodLabel() }}@if($asset->depreciation_method === 'straight_line' && $asset->useful_life_years) · {{ $asset->useful_life_years }} yr@elseif($asset->depreciation_method === 'reducing_balance') · {{ $asset->depreciation_rate }}%@endif</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Depreciated to date</small><strong class="text-warning">৳ {{ number_format($asset->accumulatedDepreciation(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Book value</small><strong class="text-success">৳ {{ number_format($asset->bookValue(), 2) }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Serial / reg no</small><strong>{{ $asset->serial_no ?: '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Location</small><strong>{{ $asset->location ?: '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Supplier</small><strong>{{ $asset->supplier ?: '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Attachment</small><strong>@if($asset->attachment)<a href="{{ asset('upload/assets/'.$asset->attachment) }}" target="_blank">Open</a>@else — @endif</strong></div>
                    @if($asset->status !== 'in_use')
                    <div class="col-md-4"><small class="text-muted d-block">Left the books</small><strong>{{ $asset->disposed_on?->format('d M Y') ?: '—' }}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Disposal proceeds</small><strong>{{ $asset->disposal_amount !== null ? '৳ '.number_format($asset->disposal_amount, 2) : '—' }}</strong></div>
                    @endif
                    @if($asset->note)
                    <div class="col-12"><small class="text-muted d-block">Note</small><strong>{{ $asset->note }}</strong></div>
                    @endif
                </div>

                <div class="progress mb-1" style="height:8px;">
                    <div class="progress-bar bg-warning" style="width: {{ $asset->depreciatedPercent() }}%"></div>
                </div>
                <small class="text-muted d-block mb-3">{{ number_format($asset->depreciatedPercent(), 1) }}% of the depreciable amount written off.</small>

                <h6 class="mb-2">Depreciation history</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Month</th><th class="text-end">Charge</th><th class="text-end">Book value after</th></tr></thead>
                        <tbody>
                            @forelse($asset->depreciations->sortByDesc('period') as $entry)
                            <tr>
                                <td>{{ $entry->period?->format('M Y') }}</td>
                                <td class="text-end">৳ {{ number_format($entry->amount, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($entry->book_value_after, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Nothing posted yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@can('assets.edit')
<div class="modal fade" id="editAsset{{ $asset->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('asset.update', $asset->id) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ $asset->asset_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.backend.assets._fields', ['prefix' => 'edit'.$asset->id, 'asset' => $asset])
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

@can('assets.dispose')
<div class="modal fade" id="disposeAsset{{ $asset->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('asset.dispose', $asset->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Dispose {{ $asset->asset_code }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        Carried at <strong>৳ {{ number_format($asset->bookValue(), 2) }}</strong> today.
                        It stops depreciating from the month it leaves; months already posted stay as they are.
                    </div>
                    <label class="form-label">What happened <span class="text-danger">*</span></label>
                    <select class="form-control dispose-status" name="status" data-amount="disposeAmount{{ $asset->id }}" required>
                        <option value="disposed">Disposed / sold</option>
                        <option value="written_off">Written off (no proceeds)</option>
                    </select>

                    <label class="form-label mt-3">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="disposed_on" value="{{ now()->toDateString() }}" required>

                    <div class="mt-3" id="disposeAmount{{ $asset->id }}">
                        <label class="form-label">Proceeds</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="disposal_amount" placeholder="0.00">
                        <small class="text-muted">What it sold for, if anything.</small>
                    </div>

                    <label class="form-label mt-3">Note</label>
                    <textarea class="form-control" name="note" rows="2">{{ $asset->note }}</textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    /**
     * A useful life only applies to straight line, and a rate only to
     * reducing balance — show whichever the chosen method actually uses.
     */
    const syncMethod = select => {
        const prefix = select.dataset.prefix;
        document.getElementById(prefix + 'LifeWrap').classList.toggle('d-none', select.value !== 'straight_line');
        document.getElementById(prefix + 'RateWrap').classList.toggle('d-none', select.value !== 'reducing_balance');
    };

    document.querySelectorAll('.asset-method').forEach(function (select) {
        syncMethod(select);
        select.addEventListener('change', () => syncMethod(select));
    });

    // Choosing a category fills in the depreciation basis that kind of asset usually takes.
    document.querySelectorAll('.asset-category').forEach(function (select) {
        select.addEventListener('change', function () {
            const option = select.selectedOptions[0];
            if (!option || !option.dataset.method) { return; }

            const form = select.closest('form');
            const method = form.querySelector('.asset-method');
            method.value = option.dataset.method;
            form.querySelector('[name="useful_life_years"]').value = option.dataset.life || '';
            form.querySelector('[name="depreciation_rate"]').value = option.dataset.rate || '';
            syncMethod(method);
        });
    });

    // Proceeds only make sense for a sale, not a write-off.
    document.querySelectorAll('.dispose-status').forEach(function (select) {
        const sync = () => document.getElementById(select.dataset.amount).classList.toggle('d-none', select.value !== 'disposed');
        sync();
        select.addEventListener('change', sync);
    });

    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const months = parseInt(btn.dataset.months, 10) || 0;
        Swal.fire({
            title: 'Delete this asset?',
            text: months > 0
                ? months + ' posted depreciation month(s) will be deleted with it. Dispose it instead to keep the history.'
                : 'This asset has no depreciation posted against it.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(r => { if (r.isConfirmed) btn.closest('form').submit(); });
    }));
});
</script>
@endsection
