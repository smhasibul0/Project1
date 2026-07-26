<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\WarehouseStock;
use App\Support\CurrentWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function index()
    {
        $stocks = WarehouseStock::with(['order', 'category', 'unit'])
            ->where('warehouse_id', CurrentWarehouse::id())
            ->latest('id')->get();

        return view('warehouse.inventory.index', [
            'warehouse' => CurrentWarehouse::get(),
            'stocks' => $stocks,
        ]);
    }

    public function show($id)
    {
        $stock = $this->findStock($id);
        $stock->load(['order', 'category', 'unit', 'movements.movedBy']);

        return view('warehouse.inventory.show', ['stock' => $stock]);
    }

    /**
     * Dispatch (remove) a quantity from a stock lot, logging a movement.
     */
    public function dispatch(Request $request, $id)
    {
        $stock = $this->findStock($id);

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:'.$stock->onHand(),
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $stock->increment('dispatched_qty', (float) $data['quantity']);
        $stock->movements()->create([
            'warehouse_id' => $stock->warehouse_id,
            'type' => 'dispatched',
            'quantity' => -1 * (float) $data['quantity'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'moved_date' => now()->toDateString(),
            'moved_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Dispatched '.$data['quantity'].' from stock.');
    }

    /**
     * Fetch a stock lot, scoped to the active warehouse.
     */
    private function findStock($id): WarehouseStock
    {
        return WarehouseStock::where('warehouse_id', CurrentWarehouse::id())->findOrFail($id);
    }
}
