<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\LcPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dollars bought against an LC with a bank account's taka, at the bank's rate. The
 * dollars never leave: they are kept in the same account's dollar balance, to pay
 * costs abroad or be sold back for taka. The payment itself is never counted as a cost.
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
            'bank_rate' => 'decimal:4',
            'bdt_amount' => 'decimal:2',
        ];
    }

    /**
     * The taka that buys the dollars: dollars × the bank's rate.
     */
    public static function takaFor(float $usdAmount, float $bankRate): float
    {
        return round($usdAmount * $bankRate, 2);
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
     * "$10,000.00 @ 123.1 — LC0005".
     */
    public function activityLabel(): string
    {
        return '$'.number_format((float) $this->usd_amount, 2).' @ '.rtrim(rtrim(number_format((float) $this->bank_rate, 4), '0'), '.')
            .($this->lc ? ' — '.$this->lc->lc_code : '');
    }
}
