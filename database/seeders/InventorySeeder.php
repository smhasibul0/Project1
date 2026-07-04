<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Unit::firstOrCreate(['name' => 'Piece'], ['short_name' => 'pc', 'conversion_factor' => 1]);
        Unit::firstOrCreate(['name' => 'Kilogram'], ['short_name' => 'kg', 'conversion_factor' => 1]);

        Category::firstOrCreate(['name' => 'General'], ['code' => 'GEN']);

        Warehouse::firstOrCreate(['name' => 'Main Warehouse'], ['code' => 'MAIN', 'is_active' => true]);
    }
}
