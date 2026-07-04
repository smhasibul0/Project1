<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Warehouse',
            'code' => strtoupper(fake()->unique()->bothify('WH-###')),
            'phone' => fake()->numerify('01#########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
