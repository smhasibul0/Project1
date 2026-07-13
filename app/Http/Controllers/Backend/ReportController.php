<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Profit & Loss across orders (accrual: full order value and all its costs,
     * regardless of what has been paid).
     */
    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $customerId = $request->input('customer_id');

        $orders = Order::with('customer')
            ->when($from, fn ($q) => $q->whereDate('order_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('order_date', '<=', $to))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('order_date')->get();

        $rows = $orders->map(function (Order $o) {
            $revenue = (float) $o->total_amount;
            $orderCost = (float) $o->total_expense;
            $lcCost = (float) $o->lc_cost;
            $containerCost = (float) $o->container_cost;
            $profit = (float) $o->profit;

            return (object) [
                'order' => $o,
                'revenue' => $revenue,
                // Supplier goods cost isn't stored on its own; back it out of the profit formula.
                'supplier_cost' => round($revenue - $profit - $orderCost - $lcCost - $containerCost, 2),
                'order_cost' => $orderCost,
                'lc_cost' => $lcCost,
                'container_cost' => $containerCost,
                'profit' => $profit,
                'margin' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0,
            ];
        });

        $totalRevenue = round($rows->sum('revenue'), 2);
        $totalProfit = round($rows->sum('profit'), 2);
        $totals = [
            'revenue' => $totalRevenue,
            'supplier_cost' => round($rows->sum('supplier_cost'), 2),
            'order_cost' => round($rows->sum('order_cost'), 2),
            'lc_cost' => round($rows->sum('lc_cost'), 2),
            'container_cost' => round($rows->sum('container_cost'), 2),
            'cost' => round($totalRevenue - $totalProfit, 2),
            'profit' => $totalProfit,
            'margin' => $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 2) : 0,
        ];

        return view('admin.backend.reports.profit_loss', [
            'rows' => $rows,
            'totals' => $totals,
            'customers' => Contact::customers()->orderBy('name')->get(),
            'from' => $from,
            'to' => $to,
            'customerId' => $customerId,
        ]);
    }

    /**
     * Outstanding receivables (orders with a balance due) with simple aging buckets.
     */
    public function receivables(Request $request)
    {
        $customerId = $request->input('customer_id');

        $orders = Order::with('customer')
            ->where('due_amount', '>', 0)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('order_date')->get();

        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];

        $rows = $orders->map(function (Order $o) use (&$buckets) {
            $age = $o->order_date ? Carbon::parse($o->order_date)->diffInDays(now()) : 0;
            $bucket = $age <= 30 ? '0-30' : ($age <= 60 ? '31-60' : ($age <= 90 ? '61-90' : '90+'));
            $buckets[$bucket] += (float) $o->due_amount;

            return (object) [
                'order' => $o,
                'age' => $age,
                'bucket' => $bucket,
            ];
        });

        return view('admin.backend.reports.receivables', [
            'rows' => $rows,
            'buckets' => $buckets,
            'totalDue' => round($orders->sum(fn ($o) => (float) $o->due_amount), 2),
            'customers' => Contact::customers()->orderBy('name')->get(),
            'customerId' => $customerId,
        ]);
    }

    /**
     * Position snapshot: payment-account balances (cash & bank) plus receivables.
     */
    public function balanceSheet()
    {
        $accounts = PaymentAccount::with('accountType')->where('is_active', true)->orderBy('name')->get();
        $grouped = $accounts->groupBy(fn ($a) => $a->accountType->name ?? 'Unclassified');

        $cashBank = round($accounts->sum(fn ($a) => (float) $a->balance), 2);
        $receivables = round((float) Order::sum('due_amount'), 2);

        return view('admin.backend.reports.balance_sheet', [
            'grouped' => $grouped,
            'cashBank' => $cashBank,
            'receivables' => $receivables,
            'total' => round($cashBank + $receivables, 2),
        ]);
    }

    /**
     * Cash flow from the ledger over a date range: money in (credits) vs out (debits),
     * broken down by source.
     */
    public function cashFlow(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $accountId = $request->input('payment_account_id');

        $transactions = Transaction::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($accountId, fn ($q) => $q->where('payment_account_id', $accountId))
            ->get();

        $labels = [
            'order_payment' => 'Order payments received',
            'deposit' => 'Deposits',
            'fund_transfer' => 'Fund transfers',
            'order_cost' => 'Order costs paid',
            'lc_cost' => 'LC charges paid',
            'container_cost' => 'Container costs paid',
            'warehouse_expense' => 'Warehouse expenses paid',
            'staff_salary' => 'Staff salaries paid',
        ];

        $rows = $transactions->groupBy('source')->map(function ($group, $source) use ($labels) {
            return (object) [
                'source' => $labels[$source] ?? ucfirst(str_replace('_', ' ', (string) $source)),
                'in' => round((float) $group->sum('credit'), 2),
                'out' => round((float) $group->sum('debit'), 2),
            ];
        })->values();

        $totalIn = round((float) $transactions->sum('credit'), 2);
        $totalOut = round((float) $transactions->sum('debit'), 2);

        return view('admin.backend.reports.cash_flow', [
            'rows' => $rows,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => round($totalIn - $totalOut, 2),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'from' => $from,
            'to' => $to,
            'accountId' => $accountId,
        ]);
    }

    /**
     * Resolve the from/to date filters (both optional).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRange(Request $request): array
    {
        return [
            $request->filled('from') ? $request->date('from')->toDateString() : null,
            $request->filled('to') ? $request->date('to')->toDateString() : null,
        ];
    }
}
