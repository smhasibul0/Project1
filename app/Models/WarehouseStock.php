<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStock extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_qty' => 'decimal:2',
            'dispatched_qty' => 'decimal:2',
            'received_date' => 'date',
        ];
    }

    /**
     * Quantity still on hand in the warehouse.
     */
    public function onHand(): float
    {
        return round((float) $this->received_qty - (float) $this->dispatched_qty, 2);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseStockMovement::class)->latest('id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
