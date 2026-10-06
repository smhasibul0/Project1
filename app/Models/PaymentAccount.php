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
    protected array $activityIgnore = ['balance'];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
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
     * Dollars sent through this account — LC payments and dollar charges. The taka
     * balance already went down by their value; this keeps count of the dollars.
     */
    public function usdSent(): float
    {
        return round((float) $this->transactions()->where('type', 'debit')->sum('usd_amount'), 2);
    }
}
