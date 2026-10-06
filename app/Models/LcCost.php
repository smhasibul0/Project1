<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\LcCostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LcCost extends Model
{
    /** @use HasFactory<LcCostFactory> */
    use HasFactory;

    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'lc';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'amount' => 'decimal:2',
            'usd_amount' => 'decimal:2',
            'usd_rate' => 'decimal:4',
        ];
    }

    public function lc(): BelongsTo
    {
        return $this->belongsTo(Lc::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CostCategory::class, 'cost_category_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
