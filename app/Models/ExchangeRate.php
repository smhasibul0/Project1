<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Carbon\CarbonInterface;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The dollar rate for one day: how many taka buy one US dollar.
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
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
            'usd_rate' => 'decimal:4',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Today, on the local clock.
     */
    public static function today(): string
    {
        return now()->timezone(config('app.display_timezone'))->toDateString();
    }

    /**
     * The rate in force on a day: that day's own, or — on a day with none set — the
     * latest one before it. Null until a rate has been set on or before that day.
     */
    public static function forDate(CarbonInterface|string|null $date = null): ?self
    {
        $day = $date === null ? static::today() : Carbon::parse($date)->toDateString();

        return static::whereDate('rate_date', '<=', $day)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The rate set for exactly this day, if there is one.
     */
    public static function onDate(CarbonInterface|string $date): ?self
    {
        return static::whereDate('rate_date', Carbon::parse($date)->toDateString())->first();
    }

    /**
     * Whether this rate was set for the given day rather than carried from an earlier one.
     */
    public function isFor(CarbonInterface|string $date): bool
    {
        return $this->rate_date->toDateString() === Carbon::parse($date)->toDateString();
    }

    /**
     * "$1 = ৳122.50 on 06 Oct 2026".
     */
    public function activityLabel(): string
    {
        return '$1 = ৳'.number_format((float) $this->usd_rate, 2).' on '.$this->rate_date->format('d M Y');
    }
}
