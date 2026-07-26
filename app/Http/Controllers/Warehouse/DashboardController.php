<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WarehouseStock;
use App\Support\CurrentWarehouse;

class DashboardController extends Controller
{
    public function index()
    {
        $warehouse = CurrentWarehouse::get();
        $warehouseId = $warehouse->id;

        $lots = WarehouseStock::where('warehouse_id', $warehouseId)->get();
        $onHandLots = $lots->filter(fn (WarehouseStock $s) => $s->onHand() > 0);

        // Orders designated here that haven't been received yet.
        $incoming = Order::with('customer')
            ->where('warehouse_id', $warehouseId)
            ->whereNotIn('goods_status', ['at_bd_warehouse', 'delivered', 'completed', 'cancelled'])
            ->orderByDesc('order_date')->get();

        $recentReceipts = WarehouseStock::with('order')
            ->where('warehouse_id', $warehouseId)
            ->latest('id')->limit(8)->get();

        return view('warehouse.dashboard', [
            'warehouse' => $warehouse,
            'totalOnHand' => round($onHandLots->sum(fn (WarehouseStock $s) => $s->onHand()), 2),
            'distinctItems' => $onHandLots->count(),
            'incomingCount' => $incoming->count(),
            'arrivedCount' => Order::where('warehouse_id', $warehouseId)->where('goods_status', 'at_bd_warehouse')->count(),
            'incoming' => $incoming->take(6),
            'recentReceipts' => $recentReceipts,
        ]);
    }
}
