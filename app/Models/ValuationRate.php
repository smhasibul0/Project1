<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\ValuationRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reference declared value for one HS code, read from a Customs valuation report:
 * the highest unit price Customs assessed, in US dollars per kilogram, kept under the
 * date it was uploaded for. Quotations use the latest one for a code.
 */
class ValuationRate extends Model
{
    /** @use HasFactory<ValuationRateFactory> */
    use HasFactory;

    use RecordsActivity;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'unit_price' => 'decimal:4',
            'bills' => 'array',
        ];
    }

    /**
     * The tariff line, when the code is in the HS code list.
     */
    public function hsCode(): BelongsTo
    {
        return $this->belongsTo(HsCode::class, 'code_digits', 'code_digits');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * The newest rate for each HS code — the one a quotation uses.
     *
     * @param  Builder<ValuationRate>  $query
     * @return Builder<ValuationRate>
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('rate_date')->orderByDesc('id');
    }

    /**
     * Whether this is the rate quotations currently use for its HS code.
     */
    public function isCurrent(): bool
    {
        return static::where('code_digits', $this->code_digits)->latestFirst()->value('id') === $this->id;
    }

    /**
     * The assessed price Customs applied most often, and on how many bills — shown
     * beside the highest so an outlier stands out.
     *
     * @return array{price: float, count: int}|null
     */
    public function mostCommonAssessedPrice(): ?array
    {
        $counts = collect($this->bills ?? [])
            ->where('currency', 'USD')
            ->countBy(fn (array $bill): string => number_format((float) $bill['assessed_unit_price'], 2, '.', ''))
            ->sortDesc();

        return $counts->isEmpty() ? null : ['price' => (float) $counts->keys()->first(), 'count' => $counts->first()];
    }

    /**
     * Write an HS code the way the tariff book prints it: 79011210 → 7901.12.10.
     */
    public static function formatCode(string $digits): string
    {
        return strlen($digits) === 8
            ? substr($digits, 0, 4).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 2)
            : $digits;
    }

    /**
     * "7901.12.10 — 5.71 USD/kg (02 Oct 2026)".
     */
    public function activityLabel(): string
    {
        return $this->hs_code.' — '.rtrim(rtrim(number_format((float) $this->unit_price, 4), '0'), '.').' USD/kg ('.$this->rate_date?->format('d M Y').')';
    }
}
