<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WarehouseStockMovement extends Model
{
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'stock';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'moved_date' => 'date',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    /**
     * "Dispatch 40 — Cotton fabric".
     */
    public function activityLabel(): string
    {
        return Str::headline((string) $this->type).' '.rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.')
            .($this->stock ? ' — '.$this->stock->item_description : '');
    }
}
