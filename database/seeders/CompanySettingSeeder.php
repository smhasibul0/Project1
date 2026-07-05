<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (CompanySetting::exists()) {
            return;
        }

        CompanySetting::create([
            'company_name' => 'Redwan Trading Corporation',
            'address' => "Dag No: 32017, House No: 94/23, Road No: 01\nKawlar Nama Para, Daksin Khan, Dhaka-1229",
            'phone' => '+880 1710924299, +880 1874537331',
            'email' => 'redwantrading24@gmail.com',
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'default_terms' => 'Net 10 Days',
            'primary_color' => '#1e40af',
            'footer_note' => 'Thank you for your business!',
            'footer_contact' => "If you have any questions about this invoice, please contact\nMobil: +880 1710924299, +880 1874537331, Email: redwantrading24@gmail.com",
        ]);
    }
}
