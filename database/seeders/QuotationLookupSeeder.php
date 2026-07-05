<?php

namespace Database\Seeders;

use App\Models\PackingType;
use App\Models\TransportationMode;
use Illuminate\Database\Seeder;

class QuotationLookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Sea', 'Air', 'Land', 'Courier'] as $mode) {
            TransportationMode::firstOrCreate(['name' => $mode]);
        }

        foreach (['Carton', 'Pallet', 'Drum', 'Bag', 'Crate', 'Wooden Case'] as $type) {
            PackingType::firstOrCreate(['name' => $type]);
        }
    }
}
