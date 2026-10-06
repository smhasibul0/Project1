<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\LcPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dollars paid against an LC from a bank account. The taka that left the account
 * is the dollars at the bank's rate; set against the day's rate, the difference is
 * the exchange gain (bank charged less) or loss (bank charged more). The payment
 * itself is never counted as a cost.
 */
class LcPayment extends Model
{
    /** @use HasFactory<LcPaymentFactory> */
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
            'paid_on' => 'date',
            'usd_amount' => 'decimal:2',
            'day_rate' => 'decimal:4',
            'bank_rate' => 'decimal:4',
            'bdt_amount' => 'decimal:2',
            'exchange_gain_loss' => 'decimal:2',
        ];
    }

    /**
     * Taka that leaves the account, and the exchange gain (+) or loss (−), for a
     * dollar amount paid at the bank's rate on a day with the given rate.
     *
     * @return array{bdt_amount: float, exchange_gain_loss: float}
     */
    public static function convert(float $usdAmount, float $dayRate, float $bankRate): array
    {
        return [
            'bdt_amount' => round($usdAmount * $bankRate, 2),
            'exchange_gain_loss' => round($usdAmount * ($dayRate - $bankRate), 2),
        ];
    }

    public function lc(): BelongsTo
    {
        return $this->belongsTo(Lc::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * "$10,000.00 @ 123.10 — LC LC0005".
     */
    public function activityLabel(): string
    {
        return '$'.number_format((float) $this->usd_amount, 2).' @ '.rtrim(rtrim(number_format((float) $this->bank_rate, 4), '0'), '.')
            .($this->lc ? ' — LC '.$this->lc->lc_code : '');
    }
}
