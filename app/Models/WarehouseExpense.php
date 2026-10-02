<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WarehouseExpense extends Model
{
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'warehouse';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WarehouseExpensePayment::class);
    }

    /**
     * Total paid so far across all payments.
     */
    public function paidTotal(): float
    {
        return round((float) $this->payments->sum(fn ($p) => (float) $p->amount), 2);
    }

    /**
     * Amount still owed on this expense.
     */
    public function dueTotal(): float
    {
        return round(max(0, (float) $this->amount - $this->paidTotal()), 2);
    }

    /**
     * Settlement state: due / partial / paid.
     */
    public function paymentStatus(): string
    {
        $paid = $this->paidTotal();

        if ($paid <= 0) {
            return 'due';
        }

        return $paid + 0.005 >= (float) $this->amount ? 'paid' : 'partial';
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
