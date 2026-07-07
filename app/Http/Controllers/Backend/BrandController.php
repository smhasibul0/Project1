<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('products')->latest()->get();

        return view('admin.backend.brands.brands', compact('brands'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['added_by'] = Auth::id();

        $brand = Brand::create($data);

        if ($request->wantsJson()) {
            return response()->json(['id' => $brand->id, 'name' => $brand->name]);
        }

        return redirect()->back()->with('success', 'Brand created successfully.');
    }

    public function update(Request $request, $id)
    {
        Brand::findOrFail($id)->update($this->validated($request));

        return redirect()->back()->with('success', 'Brand updated successfully.');
    }

    public function destroy($id)
    {
        Brand::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Brand deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
    }
}
