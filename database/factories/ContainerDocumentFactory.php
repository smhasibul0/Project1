<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\ContainerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerDocument>
 */
class ContainerDocumentFactory extends Factory
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
            'type' => fake()->randomElement(ContainerDocument::types()),
            'provided_by' => fake()->randomElement(['RTC', 'Bank']),
            'file' => fake()->uuid().'.pdf',
        ];
    }
}
