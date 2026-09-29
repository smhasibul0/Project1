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
            AssetCategorySeeder::class,
        ]);

        // The first admin login. Built without the factory, which needs Faker — a dev
        // dependency that a production install (composer install --no-dev) doesn't have.
        // Only on an empty users table, so re-seeding never brings the default login back.
        if (User::doesntExist()) {
            User::create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'email_verified_at' => now(),
                'password' => 'password',
            ]);
        }

        // Roles/permissions last so the seeded user(s) get the Admin role.
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
