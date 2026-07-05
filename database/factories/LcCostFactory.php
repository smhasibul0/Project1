<?php

namespace Database\Factories;

use App\Models\Lc;
use App\Models\LcCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LcCost>
 */
class LcCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lc_id' => Lc::factory(),
            'title' => fake()->randomElement(['LC opening charge', 'Amendment fee', 'Acceptance commission']),
            'amount' => fake()->randomFloat(2, 100, 3000),
            'cost_date' => now()->toDateString(),
        ];
    }
}
