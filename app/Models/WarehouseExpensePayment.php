<?php

namespace App\Models;

use App\Models\Concerns\HasDollarEntries;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WarehouseExpensePayment extends Model
{
    use HasDollarEntries;
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'expense';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(WarehouseExpense::class, 'warehouse_expense_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
