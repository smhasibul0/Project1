<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AccountTypeSeeder::class,
            CustomerGroupSeeder::class,
            WarehouseSeeder::class,
            QuotationLookupSeeder::class,
            CompanySettingSeeder::class,
            CostCategorySeeder::class,
            ExpenseCategorySeeder::class,
            OfficeCostTypeSeeder::class,
        ]);

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        // Roles/permissions last so the seeded user(s) get the Admin role.
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
