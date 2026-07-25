<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
            'lc_cost' => 'decimal:2',
            'container_cost' => 'decimal:2',
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

        // LCs are maintained independently — deleting an order releases its LCs
        // (clears the link) instead of deleting them.
        static::deleting(function (Order $order) {
            $order->lcs()->update(['order_id' => null]);
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

    /**
     * The designated BD warehouse the goods are received into.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
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

    public function costs(): HasMany
    {
        return $this->hasMany(OrderCost::class)->latest('id');
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(OrderTracking::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function lcs(): HasMany
    {
        return $this->hasMany(Lc::class)->latest('id');
    }

    /**
     * Containers this order is loaded into (an order may split across containers).
     */
    public function containers(): BelongsToMany
    {
        return $this->belongsToMany(Container::class, 'container_order')
            ->withPivot(['ctn', 'weight', 'cbm', 'remarks'])
            ->withTimestamps();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Total LC charges for this order (bank charges + booked LC charge lines).
     */
    public function lcCost(): float
    {
        return round((float) $this->lcs->sum(fn (Lc $lc) => $lc->lcCost()), 2);
    }

    /**
     * This order's share of every container it rides in, split by the container's basis.
     */
    public function allocatedContainerCost(): float
    {
        $sum = 0.0;

        foreach ($this->containers as $container) {
            $total = $container->costsTotal();
            $basisTotal = $container->basisTotal();
            if ($total <= 0 || $basisTotal <= 0) {
                continue;
            }

            $value = match ($container->allocation_basis) {
                'weight' => (float) $container->pivot->weight,
                'ctn' => (float) $container->pivot->ctn,
                'equal' => 1.0,
                default => (float) $container->pivot->cbm,
            };

            $sum += $total * $value / $basisTotal;
        }

        return round($sum, 2);
    }

    /**
     * Recompute every financial rollup + delivery days from the saved items, order costs,
     * payments, LC charges and allocated container costs. This is the single source of the
     * profit formula:
     *   profit = total - supplier goods cost - order costs - LC cost - container cost.
     */
    public function recomputeFinancials(): void
    {
        $this->load('items', 'costs', 'payments', 'lcs.costs', 'containers');

        $subtotal = round((float) $this->items->sum('line_total'), 2);
        $supplierCost = round((float) $this->items->sum(fn ($i) => (float) $i->supplier_asking_price * (float) $i->quantity), 2);
        $orderCosts = round((float) $this->costs->sum('amount'), 2);
        $lcCost = $this->lcCost();
        $containerCost = $this->allocatedContainerCost();

        $discount = $this->discount_type === 'percentage'
            ? round($subtotal * (float) $this->discount_value / 100, 2)
            : round((float) $this->discount_value, 2);

        $totalAmount = round($subtotal - $discount, 2);

        $received = round((float) $this->payments->sum('amount'), 2);
        $due = round($totalAmount - $received, 2);
        $lastPaymentDate = $this->payments->max('payment_date');

        $paymentStatus = $received <= 0 ? 'due' : ($received >= $totalAmount ? 'paid' : 'partial');

        $days = ($this->goods_handover_date && $this->delivered_date)
            ? Carbon::parse($this->goods_handover_date)->diffInDays(Carbon::parse($this->delivered_date))
            : null;

        $this->update([
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'total_expense' => $orderCosts,
            'lc_cost' => $lcCost,
            'container_cost' => $containerCost,
            'received_amount' => $received,
            'amount_received_date' => $lastPaymentDate,
            'due_amount' => $due,
            'payment_status' => $paymentStatus,
            'profit' => round($totalAmount - $supplierCost - $orderCosts - $lcCost - $containerCost, 2),
            'total_delivery_days' => $days,
        ]);
    }
}
