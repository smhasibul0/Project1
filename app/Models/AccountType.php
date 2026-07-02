<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
   protected $guarded = [];
   
   public function paymentAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PaymentAccount::class, 'account_type_id');
    }
}
