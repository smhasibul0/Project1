<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CostCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CostCategoryController extends Controller
{
    public function index()
    {
        $categories = CostCategory::latest()->get();

        return view('admin.backend.costs.cost_categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        CostCategory::create([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active', true),
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Cost category added.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        CostCategory::findOrFail($id)->update([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Cost category updated.');
    }

    public function destroy($id)
    {
        CostCategory::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Cost category deleted.');
    }
}
