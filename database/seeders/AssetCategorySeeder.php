<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    /**
     * Starter asset categories with the depreciation basis each kind usually
     * takes, as [name, method, useful life years, reducing-balance rate].
     *
     * @var list<array{string, string, int|null, float|null}>
     */
    private const CATEGORIES = [
        ['Land', 'none', null, null],
        ['Building', 'straight_line', 20, null],
        ['Furniture & Fixtures', 'straight_line', 10, null],
        ['Office Equipment', 'straight_line', 5, null],
        ['IT Equipment', 'straight_line', 3, null],
        ['Vehicles', 'reducing_balance', null, 20.0],
        ['Machinery', 'straight_line', 10, null],
        ['Warehouse Equipment', 'straight_line', 7, null],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as [$name, $method, $years, $rate]) {
            AssetCategory::firstOrCreate(
                ['name' => $name],
                [
                    'default_method' => $method,
                    'default_useful_life_years' => $years,
                    'default_rate' => $rate,
                    'is_active' => true,
                ]
            );
        }
    }
}
