<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends Model
{
    use HasFactory;
    use RecordsActivity;

    protected $guarded = [];

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class, 'account_type_id');
    }
}
