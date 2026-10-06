<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use RecordsActivity;

    /** Columns whose change reads "Status updated". */
    protected array $activityStatusFields = ['goods_status', 'delivery_status'];

    /** Worked out by the app, so a recalculation is never logged as somebody's edit. */
    protected array $activityIgnore = [
        'subtotal',
        'total_amount',
        'payment_status',
        'received_amount',
        'due_amount',
        'amount_received_date',
        'total_expense',
        'profit',
        'lc_cost',
        'container_cost',
        'total_cbm',
        'freight_cost',
        'duty_total',
        'total_delivery_days',
        'track_token',
    ];

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
            'cost_rate_per_cbm' => 'decimal:2',
            'sell_rate_per_cbm' => 'decimal:2',
            'total_cbm' => 'decimal:4',
            'freight_cost' => 'decimal:2',
            'duty_total' => 'decimal:2',
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

            // Order numbers run in sequence, so the public tracking link is built
            // from this instead — it can't be edited into somebody else's order.
            if (empty($order->track_token)) {
                $order->track_token = Str::random(32);
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

    public function scans(): HasMany
    {
        return $this->hasMany(OrderScan::class)->latest('id');
    }

    /**
     * How many cartons this order should amount to, taken from its item lines.
     */
    public function totalCartons(): float
    {
        return round((float) $this->items->sum('package_quantity'), 2);
    }

    /**
     * Cartons already scanned through one point of the journey. Scans add up, so
     * a consignment arriving in two lorries can be counted as each one turns up.
     */
    public function cartonsScannedAt(string $stage): float
    {
        return round((float) $this->scans->where('stage', $stage)->sum('cartons'), 2);
    }

    /**
     * Turn an arrived order's items into stock lots in the given warehouse. Idempotent:
     * an order that already has received stock is skipped so re-marking the status
     * doesn't double-count inventory.
     */
    public function receiveIntoWarehouse(int $warehouseId, ?int $userId = null): void
    {
        if ($this->warehouseStocks()->exists()) {
            return;
        }

        DB::transaction(function () use ($warehouseId, $userId) {
            foreach ($this->items as $item) {
                $qty = (float) $item->quantity;

                if ($qty <= 0) {
                    continue;
                }

                $stock = WarehouseStock::create([
                    'warehouse_id' => $warehouseId,
                    'order_id' => $this->id,
                    'order_item_id' => $item->id,
                    'item_description' => $item->item_description ?: 'Goods',
                    'received_qty' => $qty,
                    'received_date' => now()->toDateString(),
                    'added_by' => $userId,
                ]);

                $stock->movements()->create([
                    'warehouse_id' => $warehouseId,
                    'type' => 'received',
                    'quantity' => $qty,
                    'reference' => $this->order_no,
                    'moved_date' => now()->toDateString(),
                    'moved_by' => $userId,
                ]);
            }
        });
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
     * This order's share of one container's costs, split by that container's basis.
     *
     * The split needs this order's ctn / weight / cbm inside that container, which
     * rides on the pivot. A container handed over outside the relation carries no
     * pivot, so it is looked up; an order that isn't in the container pays nothing.
     */
    public function shareOfContainerCost(Container $container): float
    {
        $pivot = $container->pivot ?? $this->containers->firstWhere('id', $container->id)?->pivot;

        if ($pivot === null) {
            return 0.0;
        }

        $total = $container->costsTotal();
        $basisTotal = $container->basisTotal();

        if ($total <= 0 || $basisTotal <= 0) {
            return 0.0;
        }

        $value = match ($container->allocation_basis) {
            'weight' => (float) $pivot->weight,
            'ctn' => (float) $pivot->ctn,
            'equal' => 1.0,
            default => (float) $pivot->cbm,
        };

        return round($total * $value / $basisTotal, 2);
    }

    /**
     * This order's share of every container it rides in.
     */
    public function allocatedContainerCost(): float
    {
        return round($this->containers->sum(fn (Container $container) => $this->shareOfContainerCost($container)), 2);
    }

    /**
     * The container number(s) this order is loaded into, taken straight from the
     * containers it belongs to — an order split across two containers shows both.
     * A container that hasn't been given its number yet shows its code instead.
     */
    public function containerNumbers(): string
    {
        return $this->containers
            ->map(fn (Container $container) => $container->container_number ?: $container->container_code)
            ->filter()
            ->implode(', ');
    }

    /**
     * Recompute every financial rollup + delivery days from the saved items, order costs,
     * payments, LC charges and allocated container costs. This is the single source of the
     * profit formula:
     *   profit = total - freight - duty & taxes - order costs - LC cost - container cost.
     *
     * The goods themselves are the customer's, so nothing is bought — revenue is the
     * shipment's cubic metres at the agreed rate, and freight is what those metres cost us.
     */
    public function recomputeFinancials(): void
    {
        $this->load('items', 'costs', 'payments', 'lcs.costs', 'lcs.payments', 'containers');

        $totalCbm = round((float) $this->items->sum('cbm'), 4);
        $subtotal = round((float) $this->items->sum('line_total'), 2);
        $freightCost = round((float) $this->cost_rate_per_cbm * $totalCbm, 2);
        $dutyTotal = round((float) $this->items->sum('duty_amount'), 2);
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
            'total_cbm' => $totalCbm,
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'freight_cost' => $freightCost,
            'duty_total' => $dutyTotal,
            'total_expense' => $orderCosts,
            'lc_cost' => $lcCost,
            'container_cost' => $containerCost,
            'received_amount' => $received,
            'amount_received_date' => $lastPaymentDate,
            'due_amount' => $due,
            'payment_status' => $paymentStatus,
            'profit' => round($totalAmount - $freightCost - $dutyTotal - $orderCosts - $lcCost - $containerCost, 2),
            'total_delivery_days' => $days,
        ]);
    }

    /**
     * Store a status change by its label ("At BD Warehouse"), not its key.
     */
    protected function activityDisplayValue(string $field, mixed $value): ?string
    {
        return $field === 'goods_status' ? (static::goodsStatuses()[$value] ?? null) : null;
    }
}
