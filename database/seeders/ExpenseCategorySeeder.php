<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Seed a starter set of warehouse expense categories (parent => sub-categories).
     */
    public function run(): void
    {
        $tree = [
            'Utilities' => ['Electricity', 'Water', 'Gas', 'Internet'],
            'Rent' => ['Building', 'Storage'],
            'Transport' => ['Local Delivery', 'Fuel'],
            'Maintenance' => ['Repairs', 'Cleaning'],
            'Office' => ['Supplies', 'Miscellaneous'],
        ];

        foreach ($tree as $parentName => $children) {
            $parent = ExpenseCategory::firstOrCreate(['name' => $parentName, 'parent_id' => null]);

            foreach ($children as $childName) {
                ExpenseCategory::firstOrCreate(['name' => $childName, 'parent_id' => $parent->id]);
            }
        }
    }
}
