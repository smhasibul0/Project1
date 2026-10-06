<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Lc;
use App\Models\LcCost;
use App\Models\LcPayment;
use App\Models\Loan;
use App\Models\OfficeExpense;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\StaffSalaryPayment;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Models\WarehouseExpense;
use App\Models\WarehouseStock;
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
                'freight_cost' => (float) $o->freight_cost,
                'duty_total' => (float) $o->duty_total,
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
            'freight_cost' => round($rows->sum('freight_cost'), 2),
            'duty_total' => round($rows->sum('duty_total'), 2),
            'order_cost' => round($rows->sum('order_cost'), 2),
            'lc_cost' => round($rows->sum('lc_cost'), 2),
            'container_cost' => round($rows->sum('container_cost'), 2),
            'cost' => round($totalRevenue - $totalProfit, 2),
            'profit' => $totalProfit,
            'margin' => $totalRevenue > 0 ? round($totalProfit / $totalRevenue * 100, 2) : 0,
        ];

        // Operating expenses (warehouse overheads + staff salaries) are NOT tied to a
        // single order — they reduce the gross order profit to a company Net Profit.
        $warehouseExpenses = round((float) WarehouseExpense::query()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->sum('amount'), 2);

        // Office running costs accrue on their expense date, the same basis as
        // warehouse overheads — not when they happen to be paid.
        $officeExpenses = round((float) OfficeExpense::query()
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->sum('amount'), 2);

        $salaries = round((float) StaffSalaryPayment::query()
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->sum('amount'), 2);

        // Standalone LCs (no linked order) don't flow through any order rollup, so
        // their bank charges + charge lines join the company-level expenses here.
        $standaloneLcCharges = round(
            (float) LcCost::whereHas('lc', fn ($q) => $q->whereNull('order_id'))
                ->when($from, fn ($q) => $q->whereDate('cost_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('cost_date', '<=', $to))
                ->sum('amount')
            + (float) Lc::whereNull('order_id')
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->with('payments')
                ->get()
                ->sum(fn (Lc $lc) => $lc->bankChargesInTaka()),
            2);

        $operating = [
            'warehouse_expenses' => $warehouseExpenses,
            'office_expenses' => $officeExpenses,
            'salaries' => $salaries,
            'standalone_lc' => $standaloneLcCharges,
            'total' => round($warehouseExpenses + $officeExpenses + $salaries + $standaloneLcCharges, 2),
        ];

        // A dollar LC payment only moves money; what reaches the P&L is its exchange
        // gain or loss, counted on the day it was paid.
        $exchangeGainLoss = round((float) LcPayment::query()
            ->when($from, fn ($q) => $q->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_on', '<=', $to))
            ->when($customerId, fn ($q) => $q->whereHas('lc.order', fn ($order) => $order->where('customer_id', $customerId)))
            ->sum('exchange_gain_loss'), 2);

        $netProfit = round($totalProfit - $operating['total'] + $exchangeGainLoss, 2);

        return view('admin.backend.reports.profit_loss', [
            'rows' => $rows,
            'totals' => $totals,
            'operating' => $operating,
            'exchangeGainLoss' => $exchangeGainLoss,
            'netProfit' => $netProfit,
            'netMargin' => $totalRevenue > 0 ? round($netProfit / $totalRevenue * 100, 2) : 0,
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

        // Only loans still running carry a balance: a settled one is square and
        // a written-off one is no longer expected to change hands.
        $loans = Loan::with('payments')->where('status', 'active')->get();
        $lendings = $loans->where('direction', 'lent')->sortByDesc('start_date');
        $borrowings = $loans->where('direction', 'borrowed')->sortByDesc('start_date');

        $loansReceivable = round($lendings->sum(fn (Loan $loan) => $loan->outstanding()), 2);
        $loansPayable = round($borrowings->sum(fn (Loan $loan) => $loan->outstanding()), 2);
        $totalAssets = round($cashBank + $receivables + $loansReceivable, 2);

        return view('admin.backend.reports.balance_sheet', [
            'grouped' => $grouped,
            'cashBank' => $cashBank,
            'receivables' => $receivables,
            'loansReceivable' => $loansReceivable,
            'loansPayable' => $loansPayable,
            'lendings' => $lendings,
            'borrowings' => $borrowings,
            'total' => $totalAssets,
            'netWorth' => round($totalAssets - $loansPayable, 2),
        ]);
    }

    /**
     * Cash flow from the ledger over a date range: every money-in (credit) and money-out
     * (debit) entry across the payment accounts, with the account balance and the combined
     * balance of all accounts after each entry, plus totals broken down by source.
     */
    public function cashFlow(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $accountId = $request->input('payment_account_id');
        $type = $request->input('transaction_type');
        $source = $request->input('source');

        $transactions = Transaction::query()
            ->with(['account', 'addedBy'])
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($accountId, fn ($q) => $q->where('payment_account_id', $accountId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($source, fn ($q) => $q->where('source', $source))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();

        // Pre-select the from/to accounts of each fund transfer in the edit modal.
        Transaction::attachTransferAccounts($transactions);

        // The per-account balance after an entry is already stored on the row as
        // running_balance; the combined balance across all accounts is derived here.
        $combined = $this->combinedRunningBalances();
        foreach ($transactions as $transaction) {
            $transaction->total_balance = $combined[$transaction->id] ?? null;
        }

        $labels = $this->sourceLabels();

        $rows = $transactions->groupBy('source')->map(function ($group, $source) use ($labels) {
            return (object) [
                'source' => $labels[$source] ?? ucfirst(str_replace('_', ' ', (string) $source)),
                'in' => round((float) $group->sum('credit'), 2),
                'out' => round((float) $group->sum('debit'), 2),
                'usd_out' => round((float) $group->where('type', 'debit')->sum('usd_amount'), 2),
            ];
        })->values();

        $totalIn = round((float) $transactions->sum('credit'), 2);
        $totalOut = round((float) $transactions->sum('debit'), 2);
        $totalUsdOut = round((float) $transactions->where('type', 'debit')->sum('usd_amount'), 2);

        return view('admin.backend.reports.cash_flow', [
            'transactions' => $transactions,
            'rows' => $rows,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'totalUsdOut' => $totalUsdOut,
            'net' => round($totalIn - $totalOut, 2),
            'accounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(),
            'sourceLabels' => $labels,
            'from' => $from,
            'to' => $to,
            'accountId' => $accountId,
            'type' => $type,
            'source' => $source,
        ]);
    }

    /**
     * Human-readable names for the ledger's transaction sources.
     *
     * @return array<string, string>
     */
    private function sourceLabels(): array
    {
        return [
            'order_payment' => 'Order payments received',
            'deposit' => 'Deposits',
            'fund_transfer' => 'Fund transfers',
            'order_cost' => 'Order costs paid',
            'lc_cost' => 'LC charges paid',
            'lc_payment' => 'LC payments (dollars sent)',
            'container_cost' => 'Container costs paid',
            'warehouse_expense' => 'Warehouse expenses paid',
            'office_expense' => 'Office expenses paid',
            'staff_salary' => 'Staff salaries paid',
            'loan_received' => 'Loans received',
            'loan_repayment' => 'Loan repayments made',
            'loan_given' => 'Loans handed out',
            'loan_receipt' => 'Loan repayments received',
        ];
    }

    /**
     * Combined balance of every payment account after each ledger entry, keyed by transaction id.
     *
     * Derived the way the account book derives its per-account running balance: today's total
     * wound back by the net of the whole ledger to an opening baseline, then rolled forward
     * chronologically. The whole ledger is walked because entries outside the reported range
     * still move the balance.
     *
     * @return array<int, float>
     */
    private function combinedRunningBalances(): array
    {
        $ledger = Transaction::query()
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'credit', 'debit']);

        $net = round((float) $ledger->sum('credit') - (float) $ledger->sum('debit'), 2);
        $running = round((float) PaymentAccount::sum('balance') - $net, 2);

        $balances = [];

        foreach ($ledger as $entry) {
            $running = round($running + (float) $entry->credit - (float) $entry->debit, 2);
            $balances[$entry->id] = $running;
        }

        return $balances;
    }

    /**
     * Admin cross-warehouse overview: on-hand stock, staff, expenses & salaries per warehouse.
     */
    public function warehouseSummary()
    {
        $rows = Warehouse::withCount('staff')->orderBy('name')->get()->map(function (Warehouse $w) {
            $lots = WarehouseStock::where('warehouse_id', $w->id)->get();
            $expenses = round((float) WarehouseExpense::where('warehouse_id', $w->id)->sum('amount'), 2);
            $salaries = round((float) StaffSalaryPayment::where('warehouse_id', $w->id)->sum('amount'), 2);

            return (object) [
                'warehouse' => $w,
                'lots' => $lots->filter(fn (WarehouseStock $s) => $s->onHand() > 0)->count(),
                'on_hand' => round($lots->sum(fn (WarehouseStock $s) => $s->onHand()), 2),
                'staff' => $w->staff_count,
                'expenses' => $expenses,
                'salaries' => $salaries,
                'operating' => round($expenses + $salaries, 2),
            ];
        });

        return view('admin.backend.reports.warehouse_summary', [
            'rows' => $rows,
            'totals' => [
                'expenses' => round($rows->sum('expenses'), 2),
                'salaries' => round($rows->sum('salaries'), 2),
                'operating' => round($rows->sum('operating'), 2),
            ],
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
