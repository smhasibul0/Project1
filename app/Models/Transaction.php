<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'account';

    /** Worked out by the app, so a recalculation is never logged as somebody's edit. */
    protected array $activityIgnore = ['running_balance'];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'usd_amount' => 'decimal:2',
            'usd_rate' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Manually created entries (deposits & fund transfers) may be edited or deleted from
     * the account book; system entries (purchase/sale payments, etc.) may not.
     */
    public function isManual(): bool
    {
        return in_array($this->source, ['deposit', 'fund_transfer'], true);
    }

    /**
     * Resolve the current from/to accounts of every fund transfer in the given set so an edit
     * form can pre-select them (both legs share a transfer_group; debit = from, credit = to).
     *
     * @param  Collection<int, self>  $transactions
     */
    public static function attachTransferAccounts(Collection $transactions): void
    {
        $groups = $transactions->where('source', 'fund_transfer')->pluck('transfer_group')->filter()->unique();

        if ($groups->isEmpty()) {
            return;
        }

        $legs = self::whereIn('transfer_group', $groups)->get()->groupBy('transfer_group');

        foreach ($transactions as $transaction) {
            if ($transaction->source === 'fund_transfer' && isset($legs[$transaction->transfer_group])) {
                $pair = $legs[$transaction->transfer_group];
                $transaction->from_account_id = optional($pair->firstWhere('type', 'debit'))->payment_account_id;
                $transaction->to_account_id = optional($pair->firstWhere('type', 'credit'))->payment_account_id;
            }
        }
    }

    /**
     * Entries written by a payment are logged as that payment; only the deposits and
     * transfers made straight on the account book are logged as entries of their own.
     */
    public function shouldRecordActivity(): bool
    {
        return $this->isManual();
    }

    /**
     * "Deposit ৳5,000.00 — Payment Account City Bank".
     */
    public function activityLabel(): string
    {
        return Str::headline((string) $this->source).' '.'৳'.number_format((float) $this->amount, 2)
            .($this->account ? ' — Payment Account '.$this->account->name : '');
    }
}
