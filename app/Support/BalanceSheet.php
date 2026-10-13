<?php

namespace App\Support;

use App\Models\DollarTransaction;
use App\Models\Loan;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The balance sheet as it stood at the end of a day, worked out from the records —
 * so every past day has one, and correcting an old entry corrects it too.
 *
 * - An account holds what it holds now, less everything that moved through it
 *   after that day: its taka from the ledger, its dollars (and what they cost)
 *   from the dollar ledger. Accounts opened later did not exist yet.
 * - Receivables are the orders placed by then, less what was paid by then.
 * - A loan counts if it had started and was not yet settled or written off, at
 *   what was still owed on it that day.
 */
class BalanceSheet
{
    /**
     * @return array{date: Carbon, isToday: bool, accounts: Collection<int, PaymentAccount>, grouped: Collection<string, Collection<int, PaymentAccount>>, cashBank: float, dollarsHeld: float, dollarsAtCost: float, receivables: float, lendings: Collection<int, Loan>, borrowings: Collection<int, Loan>, loansReceivable: float, loansPayable: float, total: float, netWorth: float}
     */
    public static function asOf(?CarbonInterface $date = null): array
    {
        $zone = config('app.display_timezone');
        $date = Carbon::instance($date ?? now($zone))->setTimezone($zone)->startOfDay();
        $day = $date->toDateString();
        // The end of that day in the display timezone, as stored (UTC).
        $cutoff = $date->copy()->endOfDay()->utc();

        $accounts = static::accounts($day, $cutoff);
        $dollarsHeld = round($accounts->sum('usd_as_of'), 2);
        $dollarsAtCost = round($accounts->sum('usd_cost_as_of'), 2);
        $cashBank = round($accounts->sum('balance_as_of') + $dollarsAtCost, 2);

        $receivables = static::receivables($day, $cutoff);

        $loans = Loan::with('payments')->get()
            ->filter(fn (Loan $loan) => $loan->wasOpenOn($day))
            ->each(fn (Loan $loan) => $loan->setAttribute('outstanding_as_of', $loan->outstandingOn($day)));
        $lendings = $loans->where('direction', 'lent')->sortByDesc('start_date')->values();
        $borrowings = $loans->where('direction', 'borrowed')->sortByDesc('start_date')->values();
        $loansReceivable = round($lendings->sum('outstanding_as_of'), 2);
        $loansPayable = round($borrowings->sum('outstanding_as_of'), 2);

        $total = round($cashBank + $receivables + $loansReceivable, 2);

        return [
            'date' => $date,
            'isToday' => $date->isSameDay(now($zone)),
            'accounts' => $accounts,
            'grouped' => $accounts->groupBy(fn (PaymentAccount $a) => $a->accountType->name ?? 'Unclassified'),
            'cashBank' => $cashBank,
            'dollarsHeld' => $dollarsHeld,
            'dollarsAtCost' => $dollarsAtCost,
            'receivables' => $receivables,
            'lendings' => $lendings,
            'borrowings' => $borrowings,
            'loansReceivable' => $loansReceivable,
            'loansPayable' => $loansPayable,
            'total' => $total,
            'netWorth' => round($total - $loansPayable, 2),
        ];
    }

    /**
     * Each account opened by the end of the day, with what it held then. A closed
     * account is shown only while it still held something.
     *
     * @return Collection<int, PaymentAccount>
     */
    private static function accounts(string $day, Carbon $cutoff): Collection
    {
        $takaAfter = Transaction::where('created_at', '>', $cutoff)
            ->selectRaw('payment_account_id, SUM(credit) - SUM(debit) AS net')
            ->groupBy('payment_account_id')
            ->pluck('net', 'payment_account_id');

        $dollarsAfter = DollarTransaction::whereDate('entry_date', '>', $day)
            ->selectRaw("payment_account_id,
                SUM(CASE WHEN direction = 'in' THEN usd_amount ELSE -usd_amount END) AS usd,
                SUM(CASE WHEN direction = 'in' THEN bdt_amount ELSE -bdt_amount END) AS bdt")
            ->groupBy('payment_account_id')
            ->get()
            ->keyBy('payment_account_id');

        return PaymentAccount::with('accountType')->orderBy('name')->get()
            ->filter(fn (PaymentAccount $account) => ! $account->created_at || $account->created_at->lte($cutoff))
            ->each(function (PaymentAccount $account) use ($takaAfter, $dollarsAfter) {
                $later = $dollarsAfter->get($account->id);
                $account->setAttribute('balance_as_of', round((float) $account->balance - (float) ($takaAfter[$account->id] ?? 0), 2));
                $account->setAttribute('usd_as_of', round((float) $account->usd_balance - (float) ($later->usd ?? 0), 2));
                $account->setAttribute('usd_cost_as_of', round((float) $account->usd_cost - (float) ($later->bdt ?? 0), 2));
            })
            ->filter(fn (PaymentAccount $account) => $account->is_active
                || abs($account->balance_as_of) >= 0.005 || abs($account->usd_as_of) >= 0.005)
            ->values();
    }

    /**
     * What customers owed at the end of the day: each order placed by then, less what
     * had been paid on it by then — the same sum as an order's amount due, so a
     * payment over the total lowers it.
     */
    private static function receivables(string $day, Carbon $cutoff): float
    {
        $paid = OrderPayment::query()
            ->where(fn ($q) => $q->whereDate('payment_date', '<=', $day)
                ->orWhere(fn ($undated) => $undated->whereNull('payment_date')->where('created_at', '<=', $cutoff)))
            ->selectRaw('order_id, SUM(amount) AS paid')
            ->groupBy('order_id')
            ->pluck('paid', 'order_id');

        return round(Order::query()
            ->where(fn ($q) => $q->whereDate('order_date', '<=', $day)
                ->orWhere(fn ($undated) => $undated->whereNull('order_date')->where('created_at', '<=', $cutoff)))
            ->get(['id', 'total_amount'])
            ->sum(fn (Order $order) => (float) $order->total_amount - (float) ($paid[$order->id] ?? 0)), 2);
    }
}
