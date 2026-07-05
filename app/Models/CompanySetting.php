<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $guarded = [];

    /**
     * The single settings row, created with defaults if it does not exist yet.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
