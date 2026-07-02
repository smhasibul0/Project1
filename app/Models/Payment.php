<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'paid_on' => 'datetime',
    ];
 
    // ── Relationships ──────────────────────────────────────
 
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
 
    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
 
    public function account()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}
