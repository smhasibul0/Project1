<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStaffDocument extends Model
{
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'staff';

    protected $guarded = [];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(WarehouseStaff::class, 'warehouse_staff_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
