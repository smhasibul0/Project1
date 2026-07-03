<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Cash', 'description' => 'Physical cash on hand'],
            ['name' => 'Bank', 'description' => 'Bank accounts'],
            ['name' => 'Mobile Banking', 'description' => 'bKash, Nagad, Rocket, etc.'],
        ];

        foreach ($types as $type) {
            AccountType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
