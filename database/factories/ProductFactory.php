<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => fake()->randomFloat(2, 10, 500),
            'selling_price' => fake()->randomFloat(2, 15, 800),
            'tax_rate' => 0,
            'alert_quantity' => 10,
            'hs_code' => fake()->numerify('####.##.##'),
            'country_of_origin' => fake()->country(),
            'is_active' => true,
        ];
    }
}
