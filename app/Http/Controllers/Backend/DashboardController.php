<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Container;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Quotation;

class DashboardController extends Controller
{
    public function index()
    {
        $thisStart = now()->startOfMonth()->toDateString();
        $lastStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastEnd = now()->startOfMonth()->subDay()->toDateString();

        // ---- KPI cards: this month vs last month ----
        $ordersThis = Order::whereDate('order_date', '>=', $thisStart)->count();
        $ordersLast = Order::whereBetween('order_date', [$lastStart, $lastEnd])->count();
        $revenueThis = (float) Order::whereDate('order_date', '>=', $thisStart)->sum('total_amount');
        $revenueLast = (float) Order::whereBetween('order_date', [$lastStart, $lastEnd])->sum('total_amount');
        $profitThis = (float) Order::whereDate('order_date', '>=', $thisStart)->sum('profit');
        $profitLast = (float) Order::whereBetween('order_date', [$lastStart, $lastEnd])->sum('profit');

        $kpis = [
            'orders' => ['value' => $ordersThis, 'diff' => $ordersThis - $ordersLast, 'pct' => $this->pct($ordersThis, $ordersLast)],
            'revenue' => ['value' => $revenueThis, 'diff' => $revenueThis - $revenueLast, 'pct' => $this->pct($revenueThis, $revenueLast)],
            'profit' => ['value' => $profitThis, 'diff' => $profitThis - $profitLast, 'pct' => $this->pct($profitThis, $profitLast)],
            'due' => ['value' => (float) Order::sum('due_amount')],
        ];

        // ---- 6-month trend ----
        $trend = ['labels' => [], 'revenue' => [], 'orders' => []];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $trend['labels'][] = $m->format('M');
            $trend['revenue'][] = round((float) Order::whereYear('order_date', $m->year)->whereMonth('order_date', $m->month)->sum('total_amount'), 2);
            $trend['orders'][] = Order::whereYear('order_date', $m->year)->whereMonth('order_date', $m->month)->count();
        }

        // ---- Order pipeline (goods status distribution) ----
        $statusCounts = Order::selectRaw('goods_status, COUNT(*) as c')->groupBy('goods_status')->pluck('c', 'goods_status');
        $pipeline = collect(Order::goodsStatuses())->map(fn ($label, $key) => [
            'label' => $label,
            'count' => (int) ($statusCounts[$key] ?? 0),
        ])->filter(fn ($r) => $r['count'] > 0)->values();

        return view('admin.index', [
            'kpis' => $kpis,
            'trend' => $trend,
            'pipeline' => $pipeline,
            'recentOrders' => Order::with('customer')->latest()->take(6)->get(),
            'side' => [
                'quotationsPending' => Quotation::where('status', 'submitted')->count(),
                'containersActive' => Container::whereNotIn('status', ['delivered', 'released'])->count(),
                'customers' => Contact::customers()->count(),
                'cashBank' => round((float) PaymentAccount::where('is_active', true)->sum('balance'), 2),
            ],
            'topCustomers' => Order::selectRaw('customer_id, SUM(total_amount) as revenue, COUNT(*) as orders')
                ->whereNotNull('customer_id')->groupBy('customer_id')
                ->orderByDesc('revenue')->with('customer')->take(5)->get(),
        ]);
    }

    /**
     * Percentage change from last to this (rounded, guarded).
     */
    private function pct(float $current, float $last): float
    {
        if ($last == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $last) / abs($last)) * 100, 1);
    }
}
