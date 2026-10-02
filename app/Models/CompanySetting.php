<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usd_rate' => 'decimal:4',
            'usd_rate_date' => 'date',
        ];
    }

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
