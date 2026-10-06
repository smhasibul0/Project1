<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\LcFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lc extends Model
{
    /** @use HasFactory<LcFactory> */
    use HasFactory;

    use RecordsActivity;

    /** Columns whose change reads "Status updated". */
    protected array $activityStatusFields = ['lc_status'];

    protected $table = 'lcs';

    protected $guarded = [];

    /**
     * The ways of paying a supplier the register holds (key => short label).
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'lc' => 'LC',
            'cad' => 'CAD',
            'tt' => 'TT',
        ];
    }

    /**
     * Each type's full name (key => name).
     *
     * @return array<string, string>
     */
    public static function typeNames(): array
    {
        return [
            'lc' => 'Letter of Credit',
            'cad' => 'Cash Against Documents',
            'tt' => 'Telegraphic Transfer',
        ];
    }

    /**
     * Each type's list title (key => title).
     *
     * @return array<string, string>
     */
    public static function typeTitles(): array
    {
        return [
            'lc' => 'Letters of Credit',
            'cad' => 'Cash Against Documents',
            'tt' => 'Telegraphic Transfers',
        ];
    }

    public function typeLabel(): string
    {
        return static::types()[$this->type ?? 'lc'] ?? strtoupper((string) $this->type);
    }

    public function typeName(): string
    {
        return static::typeNames()[$this->type ?? 'lc'] ?? $this->typeLabel();
    }

    /**
     * The next free code for a type: LC0001, CAD0001, TT0001…
     */
    public static function nextCode(string $type): string
    {
        $prefix = strtoupper($type);
        $last = static::where('lc_code', 'like', $prefix.'%')->orderByDesc('id')->value('lc_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The LC lifecycle statuses (key => label).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'opened' => 'Opened',
            'released' => 'Released',
            'settled' => 'Settled',
            'cancelled' => 'Cancelled',
        ];
    }

    public function statusLabel(): string
    {
        return static::statuses()[$this->lc_status] ?? ucfirst((string) $this->lc_status);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pi_date' => 'date',
            'usd_sell_date' => 'date',
            'released_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'net_amount_received' => 'decimal:2',
            'bank_charges' => 'decimal:2',
            'usd_sell_rate' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lc $lc) {
            $lc->type ??= 'lc';

            if (empty($lc->lc_code)) {
                $lc->lc_code = static::nextCode($lc->type);
            }
        });

        // A record moved to another type takes that type's next code.
        static::updating(function (Lc $lc) {
            if ($lc->isDirty('type') && ! $lc->isDirty('lc_code')) {
                $lc->lc_code = static::nextCode($lc->type);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(LcCost::class)->latest('id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Dollar payments made against the LC, oldest first.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(LcPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    /**
     * Only dollar LCs are paid and converted; an LC in any other currency works as before.
     */
    public function isDollar(): bool
    {
        return $this->currency === 'USD';
    }

    public function usdPaid(): float
    {
        return round((float) $this->payments->sum('usd_amount'), 2);
    }

    public function usdDue(): float
    {
        return max(0.0, round((float) $this->invoice_amount - $this->usdPaid(), 2));
    }

    /**
     * The bank's rate across the LC's payments, weighted by the dollars in each.
     */
    public function averageBankRate(): ?float
    {
        $usd = (float) $this->payments->sum('usd_amount');

        return $usd > 0 ? round((float) $this->payments->sum('bdt_amount') / $usd, 4) : null;
    }

    /**
     * The rate the bank charge turns into taka at, and where it came from: the LC's
     * own payments, else the USD Sell Rate on the LC, else — as an estimate — the
     * day's rate. A charge on an LC in another currency is counted as it stands.
     *
     * @return array{rate: float|null, source: string|null}
     */
    public function bankChargeRate(): array
    {
        if (! $this->isDollar()) {
            return ['rate' => 1.0, 'source' => null];
        }

        if ($rate = $this->averageBankRate()) {
            return ['rate' => $rate, 'source' => 'payments'];
        }

        if ((float) $this->usd_sell_rate > 0) {
            return ['rate' => (float) $this->usd_sell_rate, 'source' => 'sell_rate'];
        }

        $day = ExchangeRate::forDate($this->usd_sell_date ?? $this->pi_date ?? $this->created_at);

        return $day ? ['rate' => (float) $day->usd_rate, 'source' => 'estimate'] : ['rate' => null, 'source' => null];
    }

    public function bankChargesInTaka(): float
    {
        return round((float) $this->bank_charges * (float) $this->bankChargeRate()['rate'], 2);
    }

    /**
     * A dollar bank charge with no bank rate behind it yet — estimated at the day's
     * rate, or not counted at all — so the LC is listed for a rate to be added.
     */
    public function needsBankRate(): bool
    {
        return $this->isDollar()
            && (float) $this->bank_charges > 0
            && in_array($this->bankChargeRate()['source'], ['estimate', null], true);
    }

    /**
     * The exchange gain (+) or loss (−) across the LC's payments.
     */
    public function exchangeGainLoss(): float
    {
        return round((float) $this->payments->sum('exchange_gain_loss'), 2);
    }

    /**
     * The LC's total cost to the order, in taka: bank charges plus any booked LC
     * charge lines. Neither the goods value nor the LC payments count here — the
     * payments only move money, and their exchange result is reported on its own.
     */
    public function lcCost(): float
    {
        return round($this->bankChargesInTaka() + (float) $this->costs->sum('amount'), 2);
    }
}
