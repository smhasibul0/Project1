<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\ContainerCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerCost>
 */
class ContainerCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'title' => fake()->randomElement(['Ocean freight', 'Container charge', 'THC', 'Shipping line charge']),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'cost_date' => now()->toDateString(),
        ];
    }
}
