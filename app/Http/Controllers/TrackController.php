<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    /**
     * Public order tracking: enter an order number, see its status timeline (no financials).
     */
    public function index(Request $request)
    {
        $order = null;
        $searched = $request->filled('order_no');

        if ($searched) {
            $order = Order::with(['tracking', 'customer'])
                ->where('order_no', trim($request->order_no))
                ->first();
        }

        return view('track', [
            'order' => $order,
            'searched' => $searched,
            'orderNo' => $request->order_no,
        ]);
    }
}
