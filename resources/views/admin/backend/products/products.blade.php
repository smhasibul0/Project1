@extends('admin.admin_master')
@section('admin')

<div class="content">
    <div class="container-xxl">

        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold m-0">Products</h4>
                <small class="text-muted">Manage your products</small>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Products</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Product List</h5>
                <a href="{{ route('products.create') }}" class="btn btn-primary rounded-pill px-4">
                    <i class="ri-add-line me-1"></i> Add Product
                </a>
            </div>
            <div class="card-body p-0">
                <x-data-table id="productsTable" export-name="products">
                    <table class="ct-table">
                        <thead>
                            <tr>
                                <th class="dt-noexport">#</th>
                                <th class="dt-noexport">Image</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th data-filter="Category">Category</th>
                                <th data-filter="Brand">Brand</th>
                                <th data-filter="Unit">Unit</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Stock</th>
                                <th data-filter="Country of Origin">Origin</th>
                                <th data-filter="Status">Status</th>
                                <th class="text-end dt-noexport">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $index => $product)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if($product->image)
                                        <img src="{{ asset('upload/product_images/'.$product->image) }}" alt="" width="38" height="38" style="object-fit:cover;border-radius:6px;">
                                    @else
                                        <span class="badge bg-light text-muted"><i class="ri-image-line"></i></span>
                                    @endif
                                </td>
                                <td>{{ $product->code }}</td>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->category->name ?? '—' }}</td>
                                <td>{{ $product->brand->name ?? '—' }}</td>
                                <td>{{ $product->unit->short_name ?? $product->unit->name ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format($product->cost_price, 2) }}</td>
                                <td class="text-end">৳ {{ number_format($product->selling_price, 2) }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($product->totalStock(), 2), '0'), '.') }}</td>
                                <td>{{ $product->country_of_origin ?: '—' }}</td>
                                <td>
                                    @if($product->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('product.edit', $product->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('product.delete', $product->id) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
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
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
        const form = btn.closest('form');
        Swal.fire({ title: 'Delete this product?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', confirmButtonColor: '#ef4444' })
            .then(r => { if (r.isConfirmed) form.submit(); });
    }));
});
</script>
@endsection
