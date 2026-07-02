<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $guarded = [];
    public function accountType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }


    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
