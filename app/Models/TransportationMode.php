<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class TransportationMode extends Model
{
    use RecordsActivity;

    protected $guarded = [];
}
