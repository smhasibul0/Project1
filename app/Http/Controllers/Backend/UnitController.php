<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::with('baseUnit')->latest()->get();

        return view('admin.backend.units.units', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['conversion_factor'] = $request->conversion_factor ?: 1;
        $data['added_by'] = Auth::id();

        Unit::create($data);

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request);
        $data['conversion_factor'] = $request->conversion_factor ?: 1;

        Unit::findOrFail($id)->update($data);

        return redirect()->back()->with('success', 'Unit updated successfully.');
    }

    public function destroy($id)
    {
        Unit::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Unit deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'base_unit_id' => 'nullable|exists:units,id',
            'conversion_factor' => 'nullable|numeric|min:0',
        ]);
    }
}
