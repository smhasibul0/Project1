<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeCostType extends Model
{
    use RecordsActivity;

    /**
     * A fixed cost recurs every month at a broadly steady amount (rent,
     * internet, salary); a variable one is incurred as needed (repairs,
     * stationery).
     *
     * @var list<string>
     */
    public const NATURES = ['fixed', 'variable'];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function isFixed(): bool
    {
        return $this->nature === 'fixed';
    }

    /**
     * Active fixed types carrying a standard monthly amount — the ones a
     * month's expenses can be generated from.
     */
    public function scopeGeneratable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('nature', 'fixed')
            ->where('monthly_amount', '>', 0);
    }

    /**
     * Match a type by its own name, or by the category (or parent category)
     * it is filed under, for the cost type list's search box.
     *
     * @param  Builder<OfficeCostType>  $query
     * @return Builder<OfficeCostType>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', '%'.$term.'%')
                ->orWhereHas('category', function (Builder $category) use ($term) {
                    $category->where('name', 'like', '%'.$term.'%')
                        ->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'like', '%'.$term.'%'));
                });
        });
    }

    /**
     * The sub-category — or bare top-level category — this type is filed
     * under in the shared expense category tree.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * The top-level category name, whether the type is filed against a
     * sub-category or directly against a category.
     */
    public function categoryName(): ?string
    {
        return $this->category?->parent?->name ?? $this->category?->name;
    }

    /**
     * The sub-category name, or null when the type sits directly under a
     * top-level category.
     */
    public function subCategoryName(): ?string
    {
        return $this->category?->parent ? $this->category->name : null;
    }

    /**
     * "Utilities · Electricity" for display, or null when uncategorised.
     */
    public function categoryPath(): ?string
    {
        if (! $category = $this->categoryName()) {
            return null;
        }

        return $category.($this->subCategoryName() ? ' · '.$this->subCategoryName() : '');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(OfficeExpense::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
