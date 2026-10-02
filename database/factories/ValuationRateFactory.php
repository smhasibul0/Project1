<?php

namespace Database\Factories;

use App\Models\ValuationRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValuationRate>
 */
class ValuationRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assessed = fake()->randomElement([1.2, 2.5, 5.71]);

        return [
            'rate_date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'code_digits' => '79011210',
            'hs_code' => '7901.12.10',
            'description' => 'ZINC INGOT',
            'unit_price' => $assessed,
            'bills_count' => 1,
            'bills' => [[
                'be_no' => fake()->numerify('#####'),
                'be_date' => '2026-09-27',
                'description' => 'ZINC INGOT',
                'currency' => 'USD',
                'declared_price' => 1000.0,
                'declared_unit_price' => 1.0,
                'assessed_price' => 1000 * $assessed,
                'assessed_unit_price' => $assessed,
                'net_weight' => 1000.0,
                'country' => 'IN',
                'exporter' => fake()->company(),
            ]],
            'period_from' => '2026-09-27',
            'period_to' => '2026-09-27',
        ];
    }
}
