<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Support\CurrentWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::latest()->get();

        return view('admin.backend.warehouses.warehouses', compact('warehouses'));
    }

    /**
     * Enter the warehouse portal as this warehouse (admin only) — the picked
     * warehouse is kept in the session until Exit.
     */
    public function manage($id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $warehouse = Warehouse::findOrFail($id);

        session([CurrentWarehouse::SESSION_KEY => $warehouse->id]);

        return redirect()->route('warehouse.dashboard')
            ->with('success', 'Managing '.$warehouse->name.'.');
    }

    /**
     * Leave warehouse-management mode and return to the admin panel.
     */
    public function exitManage()
    {
        session()->forget(CurrentWarehouse::SESSION_KEY);

        return redirect()->route('warehouses.index')->with('success', 'Left warehouse management.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $data['added_by'] = Auth::id();

        Warehouse::create($data);

        return redirect()->back()->with('success', 'Warehouse created successfully.');
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        Warehouse::findOrFail($id)->update($data);

        return redirect()->back()->with('success', 'Warehouse updated successfully.');
    }

    public function destroy($id)
    {
        Warehouse::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Warehouse deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);
    }
}
