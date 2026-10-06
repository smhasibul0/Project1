<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_date' => fake()->unique()->dateTimeBetween('-60 days')->format('Y-m-d'),
            'usd_rate' => fake()->randomFloat(2, 118, 126),
        ];
    }
}
