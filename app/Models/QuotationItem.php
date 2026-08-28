<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_quantity' => 'decimal:2',
            'net_weight' => 'decimal:3',
            'gross_weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'cbm' => 'decimal:4',
            'declared_value' => 'decimal:2',
            'assessable_value' => 'decimal:2',
            'cd_rate' => 'decimal:2',
            'rd_rate' => 'decimal:2',
            'sd_rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'ait_rate' => 'decimal:2',
            'at_rate' => 'decimal:2',
            'duty_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'total_profit' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * The tariff line this item is declared under.
     */
    public function hsCodeRecord(): BelongsTo
    {
        return $this->belongsTo(HsCode::class, 'hs_code_id');
    }

    public function packingType(): BelongsTo
    {
        return $this->belongsTo(PackingType::class);
    }
}
