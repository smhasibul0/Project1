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
            'supplier_asking_price' => 'decimal:2',
            'our_asking_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_profit' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'supplier_quotation_date' => 'date',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transportationMode(): BelongsTo
    {
        return $this->belongsTo(TransportationMode::class);
    }

    public function packingType(): BelongsTo
    {
        return $this->belongsTo(PackingType::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }
}
