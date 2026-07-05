<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PackingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackingTypeController extends Controller
{
    public function index()
    {
        $types = PackingType::latest()->get();

        return view('admin.backend.quotations.packing_types', compact('types'));
    }

    public function store(Request $request)
    {
        PackingType::create([
            'name' => $request->validate(['name' => 'required|string|max:255'])['name'],
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Packing type added.');
    }

    public function update(Request $request, $id)
    {
        PackingType::findOrFail($id)->update(
            $request->validate(['name' => 'required|string|max:255'])
        );

        return redirect()->back()->with('success', 'Packing type updated.');
    }

    public function destroy($id)
    {
        PackingType::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Packing type deleted.');
    }
}
