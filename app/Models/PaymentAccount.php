<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAccount extends Model
{
    use HasFactory;
    use RecordsActivity;

    /** Worked out by the app, so a recalculation is never logged as somebody's edit. */
    protected array $activityIgnore = ['balance', 'usd_balance', 'usd_cost'];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'usd_balance' => 'decimal:2',
            'usd_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_account_id');
    }

    /**
     * The dollars coming into and going out of the account's dollar balance.
     */
    public function dollarTransactions(): HasMany
    {
        return $this->hasMany(DollarTransaction::class, 'payment_account_id');
    }

    /**
     * What the dollars the account holds cost, per dollar — what spending them is costed at.
     */
    public function averageUsdRate(): ?float
    {
        return (float) $this->usd_balance > 0 ? round((float) $this->usd_cost / (float) $this->usd_balance, 4) : null;
    }
}
