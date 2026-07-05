<?php

namespace Database\Factories;

use App\Models\CostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCategory>
 */
class CostCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Freight', 'Customs', 'LC Charge', 'Local Transport', 'Service', 'Insurance']),
            'is_active' => true,
        ];
    }
}
