<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CurrentWarehouse;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('customer')
            ->where('warehouse_id', CurrentWarehouse::id())
            ->orderByDesc('order_date')->get();

        return view('warehouse.orders.index', [
            'warehouse' => CurrentWarehouse::get(),
            'incoming' => $orders->whereNotIn('goods_status', ['at_bd_warehouse', 'delivered', 'completed', 'cancelled'])->values(),
            'arrived' => $orders->whereIn('goods_status', ['at_bd_warehouse', 'delivered', 'completed'])->values(),
        ]);
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'items.category', 'items.unit', 'tracking.changedBy', 'warehouseStocks'])
            ->where('warehouse_id', CurrentWarehouse::id())
            ->findOrFail($id);

        return view('warehouse.orders.show', compact('order'));
    }
}
