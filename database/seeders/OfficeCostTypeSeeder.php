<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\OfficeCostType;
use Illuminate\Database\Seeder;

class OfficeCostTypeSeeder extends Seeder
{
    /**
     * Seed a starter set of office cost types, each filed under the shared
     * expense category tree as [name, category, sub-category, nature].
     *
     * Monthly amounts are deliberately left unset — they differ per business,
     * and only a type with an amount is picked up by monthly generation.
     *
     * @var list<array{string, string, string, string}>
     */
    private const TYPES = [
        ['Office Rent', 'Rent', 'Building', 'fixed'],
        ['Electricity Bill', 'Utilities', 'Electricity', 'fixed'],
        ['Water Bill', 'Utilities', 'Water', 'fixed'],
        ['Gas Bill', 'Utilities', 'Gas', 'fixed'],
        ['Internet Bill', 'Utilities', 'Internet', 'fixed'],
        ['Office Supplies', 'Office', 'Supplies', 'variable'],
        ['Repairs', 'Maintenance', 'Repairs', 'variable'],
        ['Cleaning', 'Maintenance', 'Cleaning', 'variable'],
        ['Local Transport', 'Transport', 'Local Delivery', 'variable'],
        ['Fuel', 'Transport', 'Fuel', 'variable'],
        ['Miscellaneous', 'Office', 'Miscellaneous', 'variable'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as [$name, $category, $subCategory, $nature]) {
            OfficeCostType::firstOrCreate(
                ['name' => $name],
                [
                    'expense_category_id' => $this->categoryId($category, $subCategory),
                    'nature' => $nature,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * The sub-category's id, creating the category pair if the expense tree
     * has not been seeded yet.
     */
    private function categoryId(string $category, string $subCategory): int
    {
        $parent = ExpenseCategory::firstOrCreate(['name' => $category, 'parent_id' => null]);

        return ExpenseCategory::firstOrCreate(['name' => $subCategory, 'parent_id' => $parent->id])->id;
    }
}
