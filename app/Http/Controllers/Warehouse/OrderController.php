<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $warehouseId = Auth::user()->warehouse_id;

        $orders = Order::with('customer')
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('order_date')->get();

        return view('warehouse.orders.index', [
            'warehouse' => Auth::user()->warehouse,
            'incoming' => $orders->whereNotIn('goods_status', ['at_bd_warehouse', 'delivered', 'completed', 'cancelled'])->values(),
            'arrived' => $orders->whereIn('goods_status', ['at_bd_warehouse', 'delivered', 'completed'])->values(),
        ]);
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'items.category', 'items.unit', 'tracking.changedBy', 'warehouseStocks'])
            ->where('warehouse_id', Auth::user()->warehouse_id)
            ->findOrFail($id);

        return view('warehouse.orders.show', compact('order'));
    }
}
