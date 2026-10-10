<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\DollarTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Dollars coming into or going out of a payment account's dollar balance, with the
 * taka they are worth at cost. Kept by App\Support\DollarLedger.
 */
class DollarTransaction extends Model
{
    /** @use HasFactory<DollarTransactionFactory> */
    use HasFactory;

    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'account';

    protected $guarded = [];

    /**
     * Where dollars come from or go to (source => label).
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'lc_payment' => 'LC payment',
        'deposit' => 'Dollar deposit',
        'transfer' => 'Transfer',
        'sale' => 'Sold for taka',
        'order_payment' => 'Customer payment',
        'order_cost' => 'Order cost',
        'container_cost' => 'Container cost',
        'lc_cost' => 'LC charge',
        'office_expense' => 'Expense',
        'warehouse_expense' => 'Warehouse expense',
        'staff_salary' => 'Salary',
        'loan' => 'Loan',
    ];

    /** The entries somebody makes on the account itself, and may reverse there. */
    public const MANUAL_SOURCES = ['deposit', 'transfer', 'sale'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'usd_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'bdt_amount' => 'decimal:2',
            'gain_loss' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isIn(): bool
    {
        return $this->direction === 'in';
    }

    public function isManual(): bool
    {
        return in_array($this->source, self::MANUAL_SOURCES, true);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }

    /**
     * What a sale brought in: the dollars at the rate they sold for.
     */
    public function proceeds(): float
    {
        return round((float) $this->usd_amount * (float) $this->rate, 2);
    }

    /**
     * "$4,000.00 @ 122.5".
     */
    public function activityLabel(): string
    {
        return ($this->isIn() ? '+' : '−').'$'.number_format((float) $this->usd_amount, 2).' @ '.static::rate($this->rate);
    }

    /**
     * A rate as people write it: 122.5, not 122.5000.
     */
    public static function rate(float|string|null $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 4), '0'), '.');
    }
}
