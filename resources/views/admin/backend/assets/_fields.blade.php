{{-- Shared asset fields for the add and edit forms.
     Expects $methods, $categories, a unique $prefix, and $asset (null when adding). --}}
@php $asset = $asset ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Asset name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="name" value="{{ $asset?->name }}" required placeholder="e.g. Delivery Van or Manager Laptop">
    </div>
    <div class="col-md-6">
        <label class="form-label">Category</label>
        <select class="form-control asset-category" name="asset_category_id" data-prefix="{{ $prefix }}">
            <option value="">-- None --</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}"
                    data-method="{{ $category->default_method }}"
                    data-life="{{ $category->default_useful_life_years }}"
                    data-rate="{{ $category->default_rate }}"
                    @selected($asset?->asset_category_id === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Picking one fills in its usual depreciation basis.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Purchase date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="purchase_date" value="{{ $asset?->purchase_date?->toDateString() ?? now()->toDateString() }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Purchase cost <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" class="form-control" name="purchase_cost" value="{{ $asset?->purchase_cost }}" required>
        @if($asset && $asset->accumulatedDepreciation() > 0)
        <small class="text-muted">Cannot go below ৳ {{ number_format($asset->accumulatedDepreciation(), 2) }} already depreciated.</small>
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label">Salvage value</label>
        <input type="number" step="0.01" min="0" class="form-control" name="salvage_value" value="{{ $asset?->salvage_value ?? '0.00' }}">
        <small class="text-muted">What it should still be worth at the end of its life.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Depreciation method <span class="text-danger">*</span></label>
        <select class="form-control asset-method" name="depreciation_method" data-prefix="{{ $prefix }}" required>
            @foreach($methods as $key => $label)
            <option value="{{ $key }}" @selected(($asset?->depreciation_method ?? 'straight_line') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4" id="{{ $prefix }}LifeWrap">
        <label class="form-label">Useful life (years) <span class="text-danger">*</span></label>
        <input type="number" min="1" max="100" class="form-control" name="useful_life_years" value="{{ $asset?->useful_life_years }}">
        <small class="text-muted">Cost less salvage, spread evenly over this many years.</small>
    </div>
    <div class="col-md-4" id="{{ $prefix }}RateWrap">
        <label class="form-label">Rate (% a year) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" max="100" class="form-control" name="depreciation_rate" value="{{ $asset?->depreciation_rate }}">
        <small class="text-muted">Charged on what the asset is still worth each month.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Serial / registration no</label>
        <input type="text" class="form-control" name="serial_no" value="{{ $asset?->serial_no }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Location</label>
        <input type="text" class="form-control" name="location" value="{{ $asset?->location }}" placeholder="Head office, Warehouse 1…">
    </div>
    <div class="col-md-4">
        <label class="form-label">Supplier / vendor</label>
        <input type="text" class="form-control" name="supplier" value="{{ $asset?->supplier }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Attachment</label>
        <input type="file" class="form-control" name="attachment">
        @if($asset?->attachment)
        <small class="text-muted"><a href="{{ asset('upload/assets/'.$asset->attachment) }}" target="_blank">Current file</a> — uploading replaces it.</small>
        @endif
    </div>
    <div class="col-12">
        <label class="form-label">Note</label>
        <textarea class="form-control" name="note" rows="1">{{ $asset?->note }}</textarea>
    </div>
</div>
