<?php

namespace Database\Seeders;

use App\Models\CostCategory;
use Illuminate\Database\Seeder;

class CostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Freight',
            'Customs / Duty',
            'LC Charge',
            'Local Transport',
            'Service / Commission',
            'Insurance',
            'Others',
        ];

        foreach ($categories as $name) {
            CostCategory::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
