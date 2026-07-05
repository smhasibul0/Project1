<?php

namespace App\Models;

use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * The shipment status pipeline (key => label).
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'booked' => 'Booked',
            'in_transit' => 'In Transit',
            'at_port' => 'At Port',
            'released' => 'Released',
            'delivered' => 'Delivered',
        ];
    }

    /**
     * Bases available for distributing container costs across member orders.
     *
     * @return array<string, string>
     */
    public static function allocationBases(): array
    {
        return [
            'cbm' => 'By CBM (volume)',
            'weight' => 'By Weight',
            'ctn' => 'By Carton count',
            'equal' => 'Equal split',
        ];
    }

    public function statusLabel(): string
    {
        return static::statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etd' => 'date',
            'eta' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Container $container) {
            if (empty($container->container_code)) {
                $last = static::where('container_code', 'like', 'CNT%')->orderByDesc('id')->value('container_code');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;
                $container->container_code = 'CNT'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Orders loaded in this container, carrying their per-container ctn/weight/cbm.
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'container_order')
            ->withPivot(['ctn', 'weight', 'cbm', 'remarks'])
            ->withTimestamps();
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ContainerCost::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContainerDocument::class)->latest('id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Total container costs to be distributed across the member orders.
     */
    public function costsTotal(): float
    {
        return round((float) $this->costs()->sum('amount'), 2);
    }

    /**
     * Sum of the allocation basis across all member orders (denominator for the split).
     */
    public function basisTotal(): float
    {
        return match ($this->allocation_basis) {
            'weight' => (float) $this->orders()->sum('container_order.weight'),
            'ctn' => (float) $this->orders()->sum('container_order.ctn'),
            'equal' => (float) $this->orders()->count(),
            default => (float) $this->orders()->sum('container_order.cbm'),
        };
    }
}
