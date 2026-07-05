<?php

namespace App\Models;

use Database\Factories\LcFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lc extends Model
{
    /** @use HasFactory<LcFactory> */
    use HasFactory;

    protected $table = 'lcs';

    protected $guarded = [];

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
            if (empty($lc->lc_code)) {
                $last = static::where('lc_code', 'like', 'LC%')->orderByDesc('id')->value('lc_code');
                $next = $last ? ((int) substr($last, 2)) + 1 : 1;
                $lc->lc_code = 'LC'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
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
     * The LC's total cost to the order: bank charges plus any booked LC charge lines.
     * (The goods value is not counted here — it already lives in the order items.)
     */
    public function lcCost(): float
    {
        return round((float) $this->bank_charges + (float) $this->costs->sum('amount'), 2);
    }
}
