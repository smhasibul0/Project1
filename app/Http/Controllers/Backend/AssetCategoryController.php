<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    public function index()
    {
        return view('admin.backend.assets.categories', [
            'categories' => AssetCategory::withCount('assets')->orderBy('name')->get(),
            'methods' => Asset::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        AssetCategory::create($data + ['added_by' => Auth::id()]);

        return redirect()->back()->with('success', 'Asset category added.');
    }

    public function update(Request $request, $id)
    {
        AssetCategory::findOrFail($id)->update($this->validated($request));

        return redirect()->back()->with('success', 'Asset category updated.');
    }

    /**
     * Deleting a category leaves its assets in place (the FK nulls out), so
     * the register never loses a row over a tidy-up.
     */
    public function destroy($id)
    {
        AssetCategory::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Asset category deleted.');
    }

    /**
     * The defaults a category hands new assets, kept consistent with how an
     * asset itself is validated: a life for straight line, a rate for
     * reducing balance, neither for something that does not depreciate.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'default_method' => ['required', Rule::in(array_keys(Asset::METHODS))],
            'default_useful_life_years' => 'nullable|integer|min:1|max:100',
            'default_rate' => 'nullable|numeric|min:0.01|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        return [
            'name' => $data['name'],
            'default_method' => $data['default_method'],
            'default_useful_life_years' => $data['default_method'] === 'straight_line' ? ($data['default_useful_life_years'] ?? null) : null,
            'default_rate' => $data['default_method'] === 'reducing_balance' ? ($data['default_rate'] ?? null) : null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
