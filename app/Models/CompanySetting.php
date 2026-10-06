<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    /**
     * The single settings row, created with defaults if it does not exist yet.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    public function activityLabel(): string
    {
        return 'Company settings';
    }
}
