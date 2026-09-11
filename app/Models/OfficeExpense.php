<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OfficeExpense extends Model
{
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

    public function costType(): BelongsTo
    {
        return $this->belongsTo(OfficeCostType::class, 'office_cost_type_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OfficeExpensePayment::class);
    }

    /**
     * Fixed or variable, inherited from the cost type. Expenses whose type was
     * deleted fall back to variable so they still total somewhere.
     */
    public function nature(): string
    {
        return $this->costType?->nature ?? 'variable';
    }

    public function isFixed(): bool
    {
        return $this->nature() === 'fixed';
    }

    /**
     * Top-level category, inherited from the cost type.
     */
    public function categoryName(): ?string
    {
        return $this->costType?->categoryName();
    }

    /**
     * Sub-category, inherited from the cost type.
     */
    public function subCategoryName(): ?string
    {
        return $this->costType?->subCategoryName();
    }

    /**
     * "Utilities · Electricity" for display, or null when the type carries no
     * category (or was deleted).
     */
    public function categoryPath(): ?string
    {
        return $this->costType?->categoryPath();
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
