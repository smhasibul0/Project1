<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'package_quantity' => 'decimal:2',
            'net_weight' => 'decimal:3',
            'cbm' => 'decimal:4',
            'actual_weight' => 'decimal:3',
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
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The tariff line this item is declared under.
     */
    public function hsCodeRecord(): BelongsTo
    {
        return $this->belongsTo(HsCode::class, 'hs_code_id');
    }
}
