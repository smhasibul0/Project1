<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStaffDocument extends Model
{
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
