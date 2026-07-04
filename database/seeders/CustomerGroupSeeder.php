<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['name' => 'General', 'discount_type' => 'percentage', 'discount_amount' => 0],
            ['name' => 'Wholesale', 'discount_type' => 'percentage', 'discount_amount' => 5],
        ];

        foreach ($groups as $group) {
            CustomerGroup::firstOrCreate(['name' => $group['name']], $group);
        }
    }
}
