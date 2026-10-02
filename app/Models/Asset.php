<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Carbon\Carbon;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * Straight line spreads the depreciable amount evenly over the useful
     * life; reducing balance charges a fixed percentage of what the asset is
     * still worth; none is for things that do not depreciate, such as land.
     *
     * @var array<string, string>
     */
    public const METHODS = [
        'straight_line' => 'Straight line',
        'reducing_balance' => 'Reducing balance',
        'none' => 'Does not depreciate',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'in_use' => 'In use',
        'disposed' => 'Disposed',
        'written_off' => 'Written off',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'disposed_on' => 'date',
            'purchase_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'disposal_amount' => 'decimal:2',
            'depreciation_rate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * The next code in sequence, e.g. AST-0007.
     */
    public static function nextCode(): string
    {
        $last = static::orderByDesc('id')->value('asset_code');
        $number = $last && preg_match('/(\d+)$/', $last, $match) ? ((int) $match[1]) + 1 : 1;

        return 'AST-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Assets still on the books — a disposed or written off asset stops
     * depreciating from the month it left.
     */
    public function scopeOnBooks(Builder $query): Builder
    {
        return $query->where('status', 'in_use');
    }

    /**
     * The amount that gets written off across the asset's life: what it cost
     * less what it is expected to fetch at the end.
     */
    public function depreciableBase(): float
    {
        return round(max(0, (float) $this->purchase_cost - (float) $this->salvage_value), 2);
    }

    /**
     * Everything charged against this asset so far.
     */
    public function accumulatedDepreciation(): float
    {
        return round((float) $this->depreciations->sum(fn (AssetDepreciation $d) => (float) $d->amount), 2);
    }

    /**
     * What the asset is carried at today: cost less everything charged.
     */
    public function bookValue(): float
    {
        return round((float) $this->purchase_cost - $this->accumulatedDepreciation(), 2);
    }

    /**
     * Whether the asset has been written down as far as it can go.
     */
    public function isFullyDepreciated(): bool
    {
        return $this->bookValue() <= (float) $this->salvage_value + 0.005;
    }

    /**
     * The share of the base already written off, for the progress readout.
     */
    public function depreciatedPercent(): float
    {
        $base = $this->depreciableBase();

        return $base > 0 ? round(min(100, $this->accumulatedDepreciation() / $base * 100), 2) : 0.0;
    }

    /**
     * The charge for one month, never taking the asset below its salvage
     * value and never starting before it was bought.
     *
     * Straight line divides the depreciable base over the useful life;
     * reducing balance takes its annual rate off what is still on the books.
     */
    public function monthlyCharge(Carbon $period): float
    {
        if ($this->depreciation_method === 'none' || $this->status !== 'in_use') {
            return 0.0;
        }

        // Nothing is charged for a month that ends before the asset was bought.
        if ($this->purchase_date && $period->copy()->endOfMonth()->lt($this->purchase_date)) {
            return 0.0;
        }

        $remaining = round($this->bookValue() - (float) $this->salvage_value, 2);
        if ($remaining <= 0) {
            return 0.0;
        }

        $charge = $this->depreciation_method === 'reducing_balance'
            ? $this->bookValue() * ((float) $this->depreciation_rate / 100) / 12
            : $this->straightLineMonthly();

        // The last month tops up to the salvage value rather than overshooting.
        return round(min(max(0, $charge), $remaining), 2);
    }

    /**
     * One month of straight-line depreciation.
     */
    private function straightLineMonthly(): float
    {
        $months = (int) $this->useful_life_years * 12;

        return $months > 0 ? $this->depreciableBase() / $months : 0.0;
    }

    /**
     * Whether a month can still be charged against this asset.
     */
    public function isDepreciableFor(Carbon $period): bool
    {
        return $this->monthlyCharge($period) > 0;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->depreciation_method] ?? $this->depreciation_method;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
