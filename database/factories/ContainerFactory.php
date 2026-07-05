<?php

namespace Database\Factories;

use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_no' => fake()->unique()->numberBetween(1, 999).'/2026 (CTG)',
            'container_number' => strtoupper(fake()->bothify('????#######')),
            'transport_mode' => 'Sea Transport',
            'shipping_line' => fake()->randomElement(['Maersk', 'MSC', 'CMA CGM']),
            'status' => 'booked',
            'allocation_basis' => 'cbm',
        ];
    }
}
