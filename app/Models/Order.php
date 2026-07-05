<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * The goods-status pipeline (key => label).
     *
     * @return array<string, string>
     */
    public static function goodsStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'sourcing' => 'Sourcing',
            'at_china_warehouse' => 'At China Warehouse',
            'shipped' => 'Shipped',
            'at_port' => 'At Port',
            'at_bd_warehouse' => 'At BD Warehouse',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public function statusLabel(): string
    {
        return static::goodsStatuses()[$this->goods_status] ?? ucfirst(str_replace('_', ' ', (string) $this->goods_status));
    }

    /**
     * Record a status change on the tracking timeline.
     */
    public function logStatus(string $status, ?string $note = null, ?int $userId = null): void
    {
        $this->tracking()->create([
            'status' => $status,
            'note' => $note,
            'changed_by' => $userId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'goods_handover_date' => 'date',
            'tentative_receive_date' => 'date',
            'port_arrival_date' => 'date',
            'bd_warehouse_date' => 'date',
            'delivered_date' => 'date',
            'amount_received_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'profit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_no)) {
                $last = static::where('order_no', 'like', 'OR%')->orderByDesc('id')->value('order_no');
                $next = $last ? ((int) substr($last, 2)) + 1 : 1;
                $order->order_no = 'OR'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function transportationMode(): BelongsTo
    {
        return $this->belongsTo(TransportationMode::class);
    }

    public function packingType(): BelongsTo
    {
        return $this->belongsTo(PackingType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(OrderExpense::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(OrderTracking::class)->latest('id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
