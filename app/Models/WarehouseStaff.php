<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStaff extends Model
{
    protected $table = 'warehouse_staff';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'join_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WarehouseStaffDocument::class)->latest('id');
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(StaffSalaryPayment::class)->latest('payment_date');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Total paid to this staff member for a salary month (YYYY-MM).
     */
    public function paidForMonth(string $month): float
    {
        return round((float) $this->salaryPayments->where('salary_month', $month)->sum(fn ($p) => (float) $p->amount), 2);
    }

    /**
     * Remaining salary due for a month (0 when no monthly salary is set).
     */
    public function dueForMonth(string $month): float
    {
        return round(max(0, (float) $this->monthly_salary - $this->paidForMonth($month)), 2);
    }

    /**
     * Settlement state of a month's salary: paid / partial / due.
     */
    public function salaryStatusForMonth(string $month): string
    {
        $paid = $this->paidForMonth($month);

        if ($paid + 0.005 >= (float) $this->monthly_salary && ((float) $this->monthly_salary > 0 || $paid > 0)) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'due';
    }
}
