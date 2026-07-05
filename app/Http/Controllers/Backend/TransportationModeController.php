<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TransportationMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransportationModeController extends Controller
{
    public function index()
    {
        $modes = TransportationMode::latest()->get();

        return view('admin.backend.quotations.transportation_modes', compact('modes'));
    }

    public function store(Request $request)
    {
        TransportationMode::create([
            'name' => $request->validate(['name' => 'required|string|max:255'])['name'],
            'added_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Transportation mode added.');
    }

    public function update(Request $request, $id)
    {
        TransportationMode::findOrFail($id)->update(
            $request->validate(['name' => 'required|string|max:255'])
        );

        return redirect()->back()->with('success', 'Transportation mode updated.');
    }

    public function destroy($id)
    {
        TransportationMode::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Transportation mode deleted.');
    }
}
