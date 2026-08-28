<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Goods lots received into this warehouse from orders.
     */
    public function receivedStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(WarehouseExpense::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(WarehouseStaff::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(StaffSalaryPayment::class);
    }

    /**
     * Login accounts assigned to this warehouse (warehouse-portal users).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
