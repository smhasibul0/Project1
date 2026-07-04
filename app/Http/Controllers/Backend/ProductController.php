<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'brand', 'unit', 'stocks'])->latest()->get();

        return view('admin.backend.products.products', compact('products'));
    }

    public function create()
    {
        return view('admin.backend.products.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['added_by'] = Auth::id();

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request);
        }

        $product = Product::create($data);

        $this->syncStock($product, $request->input('stock', []));

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit($id)
    {
        $product = Product::with('stocks')->findOrFail($id);

        return view('admin.backend.products.edit', array_merge($this->formData(), [
            'product' => $product,
            'stockByWarehouse' => $product->stocks->pluck('quantity', 'warehouse_id'),
        ]));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $this->validated($request, $product->id);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $data['image'] = $this->storeImage($request);
        }

        $product->update($data);

        $this->syncStock($product, $request->input('stock', []));

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $this->deleteImage($product->image);
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    /**
     * Shared category/brand/unit/warehouse lists for the create & edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', 1)->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:products,code'.($ignoreId ? ','.$ignoreId : ''),
            'barcode' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'required|exists:units,id',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'alert_quantity' => 'nullable|numeric|min:0',
            'hs_code' => 'nullable|string|max:100',
            'country_of_origin' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);
    }

    /**
     * Set each warehouse's stock quantity for the product (opening stock / manual adjustment).
     *
     * @param  array<int|string, mixed>  $stock
     */
    private function syncStock(Product $product, array $stock): void
    {
        foreach ($stock as $warehouseId => $quantity) {
            if ($quantity === null || $quantity === '') {
                continue;
            }

            $product->stocks()->updateOrCreate(
                ['warehouse_id' => $warehouseId],
                ['quantity' => (float) $quantity],
            );
        }
    }

    private function storeImage(Request $request): string
    {
        $manager = new ImageManager(new Driver);
        $image = $request->file('image');
        $name = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();

        $manager->read($image)->cover(500, 500)->save(public_path('upload/product_images/'.$name));

        return $name;
    }

    private function deleteImage(?string $image): void
    {
        if ($image && file_exists(public_path('upload/product_images/'.$image))) {
            unlink(public_path('upload/product_images/'.$image));
        }
    }
}
