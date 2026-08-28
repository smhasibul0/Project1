<?php

namespace App\Models;

use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * The quotation lifecycle (key => label).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'draft' => 'Draft',
            'requested' => 'Requested',       // customer submitted a request
            'quoted' => 'Quoted',             // admin priced it & sent to customer
            'accepted' => 'Accepted',         // customer accepted
            'negotiating' => 'Negotiating',   // customer wants to negotiate
            'rejected' => 'Rejected',
            'converted' => 'Converted',
        ];
    }

    public function statusLabel(): string
    {
        return static::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query_received_date' => 'date',
            'submitted_to_customer' => 'boolean',
            'grand_total' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'total_duty' => 'decimal:2',
            'freight_rate' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'sell_rate_per_cbm' => 'decimal:2',
            'total_cbm' => 'decimal:4',
            'customer_charge' => 'decimal:2',
            'projected_cost_total' => 'decimal:2',
            'projected_profit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quotation $quotation) {
            if (empty($quotation->quotation_no)) {
                $last = static::where('quotation_no', 'like', 'Q%')->orderByDesc('id')->value('quotation_no');
                $next = $last ? ((int) substr($last, 1)) + 1 : 1;
                $quotation->quotation_no = 'Q'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function transportationMode(): BelongsTo
    {
        return $this->belongsTo(TransportationMode::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(QuotationExpense::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
