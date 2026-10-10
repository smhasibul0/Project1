<?php

namespace App\Support;

use App\Models\DollarTransaction;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The dollars a payment account holds, at what they cost in taka.
 *
 * Dollars come in at a rate (an LC payment at the bank's rate, a deposit at the rate
 * given) and their taka cost is added to the account's. Dollars going out are costed
 * at the account's average — what the dollars it holds cost, per dollar — so spending
 * them makes no gain or loss. Only selling them does: the taka they sold for, against
 * what they cost.
 *
 * Every call locks the account row, so run it inside the caller's DB transaction.
 */
class DollarLedger
{
    /**
     * What the given dollars cost from an account, in taka.
     *
     * @throws ValidationException when the account holds fewer dollars
     */
    public static function costOf(PaymentAccount|int $account, float $usd, string $field = 'usd_amount'): float
    {
        $account = static::locked($account);
        static::ensureHolds($account, $usd, $field);

        return static::costFrom($account, $usd);
    }

    /**
     * What the given dollars cost from an account, when they settle something owed:
     * they may not come to more than is owed.
     *
     * @throws ValidationException when the account holds fewer dollars, or they come to more
     */
    public static function costUpTo(PaymentAccount|int $account, float $usd, float $owed, string $field = 'usd_amount'): float
    {
        $taka = static::costOf($account, $usd, $field);

        if ($taka > $owed + 0.005) {
            throw ValidationException::withMessages([
                $field => '$'.number_format($usd, 2).' comes to ৳'.number_format($taka, 2)
                    .' at what the dollars cost — more than the ৳'.number_format($owed, 2).' owed.',
            ]);
        }

        return $taka;
    }

    /**
     * Dollars into an account, costing the taka given or — without one — dollars × rate.
     *
     * @param  array{entry_date?: mixed, description?: string|null, reference?: string|null, note?: string|null, transfer_group?: string|null}  $details
     */
    public static function receive(PaymentAccount|int $account, float $usd, float $rate, string $source, array $details = [], ?Model $for = null, ?float $bdt = null): DollarTransaction
    {
        $account = static::locked($account);
        $bdt ??= round($usd * $rate, 2);

        $account->update([
            'usd_balance' => round((float) $account->usd_balance + $usd, 2),
            'usd_cost' => round((float) $account->usd_cost + $bdt, 2),
        ]);

        return static::record($account, 'in', $source, $usd, $rate, $bdt, $details, $for);
    }

    /**
     * Dollars out of an account, costed at what the dollars it holds cost on average.
     *
     * @param  array{entry_date?: mixed, description?: string|null, reference?: string|null, note?: string|null, transfer_group?: string|null}  $details
     *
     * @throws ValidationException when the account holds fewer dollars
     */
    public static function spend(PaymentAccount|int $account, float $usd, string $source, array $details = [], ?Model $for = null, string $field = 'usd_amount'): DollarTransaction
    {
        $account = static::locked($account);
        static::ensureHolds($account, $usd, $field);
        $bdt = static::costFrom($account, $usd);

        static::take($account, $usd, $bdt);

        return static::record($account, 'out', $source, $usd, round($bdt / $usd, 4), $bdt, $details, $for);
    }

    /**
     * Sell dollars for taka at the rate given. The taka comes into the same account; what
     * it fetches over (or under) what the dollars cost is the exchange gain (or loss).
     *
     * @param  array{entry_date?: mixed, reference?: string|null, note?: string|null}  $details
     */
    public static function sell(PaymentAccount|int $account, float $usd, float $rate, array $details = []): DollarTransaction
    {
        $account = static::locked($account);
        static::ensureHolds($account, $usd, 'usd_amount');
        $cost = static::costFrom($account, $usd);
        $proceeds = round($usd * $rate, 2);

        static::take($account, $usd, $cost);

        $sale = static::record($account, 'out', 'sale', $usd, $rate, $cost, $details + [
            'description' => 'Sold $'.number_format($usd, 2).' @ '.DollarTransaction::rate($rate),
        ], null);
        $sale->update(['gain_loss' => round($proceeds - $cost, 2)]);

        $account->increment('balance', $proceeds);
        $account->transactions()->create([
            'type' => 'credit',
            'source' => 'dollar_sale',
            'amount' => $proceeds,
            'credit' => $proceeds,
            'debit' => 0,
            'running_balance' => $account->fresh()->balance,
            'description' => $sale->description,
            'reference' => $details['reference'] ?? null,
            'note' => $details['note'] ?? null,
            'usd_amount' => $usd,
            'usd_rate' => $rate,
            'transactionable_type' => DollarTransaction::class,
            'transactionable_id' => $sale->id,
            'added_by' => Auth::id(),
            'created_at' => $sale->entry_date,
        ]);

        return $sale;
    }

    /**
     * Move dollars between accounts. They keep what they cost, so nothing is gained or lost.
     *
     * @param  array{entry_date?: mixed, reference?: string|null, note?: string|null}  $details
     */
    public static function transfer(PaymentAccount|int $from, PaymentAccount|int $to, float $usd, array $details = []): DollarTransaction
    {
        $from = static::locked($from);
        $to = static::locked($to);
        $details['transfer_group'] = (string) Str::uuid();

        $out = static::spend($from, $usd, 'transfer', $details + ['description' => 'Transfer to '.$to->name]);
        static::receive($to, $usd, (float) $out->rate, 'transfer', $details + ['description' => 'Transfer from '.$from->name], null, (float) $out->bdt_amount);

        return $out;
    }

    /**
     * Undo every dollar movement made for a record. True when it had any — the record
     * was paid in dollars, so there is no taka to put back.
     */
    public static function reverseFor(Model $record): bool
    {
        $entries = DollarTransaction::where('transactionable_type', $record->getMorphClass())
            ->where('transactionable_id', $record->getKey())
            ->orderByDesc('id')
            ->get();

        $entries->each(fn (DollarTransaction $entry) => static::reverse($entry));

        return $entries->isNotEmpty();
    }

    /**
     * Undo one movement: dollars that came in go back out (when the account still holds
     * them), dollars that went out come back at what they cost. A sale also takes back its
     * taka; a transfer is undone on both sides.
     *
     * @throws ValidationException when dollars that came in have since been spent
     */
    public static function reverse(DollarTransaction $entry): void
    {
        $legs = $entry->transfer_group
            ? DollarTransaction::where('transfer_group', $entry->transfer_group)->orderBy('direction')->get()
            : collect([$entry]);

        foreach ($legs as $leg) {
            $account = static::locked($leg->payment_account_id);

            if ($leg->isIn()) {
                static::ensureHolds($account, (float) $leg->usd_amount, 'usd_amount', 'have already been spent');
                static::take($account, (float) $leg->usd_amount, (float) $leg->bdt_amount);
            } else {
                $account->update([
                    'usd_balance' => round((float) $account->usd_balance + (float) $leg->usd_amount, 2),
                    'usd_cost' => round((float) $account->usd_cost + (float) $leg->bdt_amount, 2),
                ]);
            }

            if ($leg->source === 'sale') {
                $taka = Transaction::where('transactionable_type', DollarTransaction::class)->where('transactionable_id', $leg->id)->first();
                if ($taka) {
                    $account->decrement('balance', (float) $taka->credit);
                    $taka->delete();
                }
            }

            $leg->delete();
        }
    }

    /**
     * A form's rules, extended to pay in taka or dollars: in dollars it needs the
     * account the dollars come from (or go into) and the dollar amount — and, for
     * money coming in, the rate. The taka amount is worked out from them.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function withRules(array $rules, string $accountField = 'payment_account_id', bool $withRate = false): array
    {
        $rules[$accountField] = trim(($rules[$accountField] ?? 'nullable|exists:payment_accounts,id').'|required_if:currency,USD', '|');

        return $rules + [
            'currency' => 'nullable|in:BDT,USD',
            'usd_amount' => 'nullable|required_if:currency,USD|numeric|min:0.01',
        ] + ($withRate ? ['usd_rate' => 'nullable|required_if:currency,USD|numeric|min:0.0001|max:100000'] : []);
    }

    /**
     * The taka amount rule for such a form: given in taka, worked out in dollars.
     */
    public static function amountRule(string $extra = ''): string
    {
        return 'required_unless:currency,USD|nullable|numeric|min:0.01'.($extra !== '' ? '|'.$extra : '');
    }

    /**
     * Whether a validated form chose to pay in dollars.
     *
     * @param  array<string, mixed>  $data
     */
    public static function inDollars(array $data): bool
    {
        return ($data['currency'] ?? 'BDT') === 'USD';
    }

    private static function locked(PaymentAccount|int $account): PaymentAccount
    {
        return PaymentAccount::whereKey($account instanceof PaymentAccount ? $account->getKey() : $account)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private static function ensureHolds(PaymentAccount $account, float $usd, string $field, string $reason = 'are needed'): void
    {
        if ($usd > (float) $account->usd_balance + 0.005) {
            throw ValidationException::withMessages([
                $field => $account->name.' holds $'.number_format((float) $account->usd_balance, 2)
                    .' — $'.number_format($usd, 2).' '.$reason.'.',
            ]);
        }
    }

    /**
     * The taka cost of dollars taken from what an account holds. Taking every dollar
     * takes all the cost left, so rounding never strands taka on an empty balance.
     */
    private static function costFrom(PaymentAccount $account, float $usd): float
    {
        if (abs($usd - (float) $account->usd_balance) < 0.005) {
            return (float) $account->usd_cost;
        }

        return round($usd * (float) $account->usd_cost / (float) $account->usd_balance, 2);
    }

    private static function take(PaymentAccount $account, float $usd, float $bdt): void
    {
        $balance = round((float) $account->usd_balance - $usd, 2);

        $account->update([
            'usd_balance' => max(0, $balance),
            'usd_cost' => $balance <= 0 ? 0 : max(0, round((float) $account->usd_cost - $bdt, 2)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function record(PaymentAccount $account, string $direction, string $source, float $usd, float $rate, float $bdt, array $details, ?Model $for): DollarTransaction
    {
        return $account->dollarTransactions()->create([
            'entry_date' => Carbon::parse($details['entry_date'] ?? now())->toDateString(),
            'direction' => $direction,
            'source' => $source,
            'usd_amount' => $usd,
            'rate' => $rate,
            'bdt_amount' => $bdt,
            'description' => $details['description'] ?? null,
            'reference' => $details['reference'] ?? null,
            'note' => $details['note'] ?? null,
            'transfer_group' => $details['transfer_group'] ?? null,
            'transactionable_type' => $for?->getMorphClass(),
            'transactionable_id' => $for?->getKey(),
            'added_by' => Auth::id(),
        ]);
    }
}
