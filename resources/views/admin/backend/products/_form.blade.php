@php
    $product = $product ?? null;
    $stockByWarehouse = $stockByWarehouse ?? collect();
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Product Details</h6></div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="{{ old('name', $product->name ?? '') }}" required>
                    @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code / SKU</label>
                    <input type="text" class="form-control" name="code" value="{{ old('code', $product->code ?? '') }}" placeholder="Auto-generated if blank">
                    @error('code')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select class="form-control" name="category_id" required>
                        <option value="">-- Select --</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id ?? '') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Brand</label>
                    <select class="form-control" name="brand_id">
                        <option value="">-- None --</option>
                        @foreach($brands as $b)
                            <option value="{{ $b->id }}" @selected(old('brand_id', $product->brand_id ?? '') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                    <select class="form-control" name="unit_id" required>
                        <option value="">-- Select --</option>
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" @selected(old('unit_id', $product->unit_id ?? '') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    @error('unit_id')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Cost Price</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="selling_price" value="{{ old('selling_price', $product->selling_price ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="tax_rate" value="{{ old('tax_rate', $product->tax_rate ?? 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Alert Quantity</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="alert_quantity" value="{{ old('alert_quantity', $product->alert_quantity ?? 0) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Barcode</label>
                    <input type="text" class="form-control" name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2">{{ old('description', $product->description ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Opening Stock</h6></div>
            <div class="card-body">
                @if($warehouses->isEmpty())
                    <p class="text-muted mb-0">No warehouses yet. <a href="{{ route('warehouses.index') }}">Add a warehouse</a> to set stock.</p>
                @else
                    <div class="row g-3">
                        @foreach($warehouses as $w)
                        <div class="col-md-4">
                            <label class="form-label">{{ $w->name }}</label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                   name="stock[{{ $w->id }}]"
                                   value="{{ old('stock.'.$w->id, $stockByWarehouse[$w->id] ?? '') }}"
                                   placeholder="0">
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Import / Export</h6></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">HS / Customs Code</label>
                    <input type="text" class="form-control" name="hs_code" value="{{ old('hs_code', $product->hs_code ?? '') }}" placeholder="e.g. 8471.30.00">
                </div>
                <div class="col-12">
                    <label class="form-label">Country of Origin</label>
                    <input type="text" class="form-control" name="country_of_origin" value="{{ old('country_of_origin', $product->country_of_origin ?? '') }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Image &amp; Status</h6></div>
            <div class="card-body">
                @if($product && $product->image)
                    <img src="{{ asset('upload/product_images/'.$product->image) }}" alt="" class="mb-2" width="90" height="90" style="object-fit:cover;border-radius:8px;">
                @endif
                <label class="form-label">Product Image</label>
                <input type="file" class="form-control" name="image" accept="image/*">
                @error('image')<small class="text-danger">{{ $message }}</small>@enderror

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
                    <label class="form-check-label fw-semibold">Active</label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">{{ $product ? 'Update Product' : 'Save Product' }}</button>
            <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</div>
