<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\DutyCalculator;
use Database\Factories\HsCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HsCode extends Model
{
    /** @use HasFactory<HsCodeFactory> */
    use HasFactory;

    use RecordsActivity;

    /** Worked out by the app, so a recalculation is never logged as somebody's edit. */
    protected array $activityIgnore = ['code_digits'];

    protected $guarded = [];

    /**
     * The duty/tax rate columns, in the order Customs prints them.
     *
     * @var array<int, string>
     */
    public const RATE_FIELDS = ['cd_rate', 'sd_rate', 'vat_rate', 'ait_rate', 'rd_rate', 'at_rate'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cd_rate' => 'decimal:2',
            'sd_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'ait_rate' => 'decimal:2',
            'rd_rate' => 'decimal:2',
            'at_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // code_digits always follows code, however the row was written.
        static::saving(function (HsCode $hsCode) {
            $hsCode->code_digits = static::digits($hsCode->code);
        });
    }

    /**
     * Strip a tariff code down to its digits (0101.21.00 -> 01012100).
     */
    public static function digits(?string $code): string
    {
        return preg_replace('/\D/', '', (string) $code) ?? '';
    }

    /**
     * Match a code or any word of the description. Digits typed with or without
     * dots both hit code_digits, so "3911.90" and "391190" find the same row.
     *
     * @param  Builder<HsCode>  $query
     * @return Builder<HsCode>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = static::digits($term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            if ($digits !== '') {
                $q->where('code_digits', 'like', $digits.'%')
                    ->orWhere('code', 'like', $term.'%');
            }

            $q->orWhere('description', 'like', '%'.$term.'%');
        });
    }

    /**
     * The six rates in the shape DutyCalculator::calculate() expects.
     *
     * @return array<string, float>
     */
    public function rates(): array
    {
        return array_map(fn (string $field): float => (float) $this->{$field}, array_combine(self::RATE_FIELDS, self::RATE_FIELDS));
    }

    /**
     * Total tax incidence for one unit of assessable value, as a percentage —
     * the "For Total Tax Incidence" figure on the Customs tariff lookup.
     */
    public function totalTaxIncidence(): float
    {
        return round(DutyCalculator::calculate(100, $this->rates())['total'], 2);
    }

    /**
     * The newest Customs valuation rate uploaded for this code — the reference a
     * quotation's declared value is worked out from.
     */
    public function latestValuationRate(): HasOne
    {
        return $this->hasOne(ValuationRate::class, 'code_digits', 'code_digits')
            ->ofMany(['rate_date' => 'max', 'id' => 'max']);
    }
}
