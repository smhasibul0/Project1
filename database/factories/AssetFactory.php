<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 20000, 500000);

        return [
            'asset_code' => Asset::nextCode(),
            'name' => fake()->randomElement(['Office Desk', 'Delivery Van', 'Laptop', 'Forklift', 'Air Conditioner']),
            'purchase_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'purchase_cost' => $cost,
            'salvage_value' => round($cost * 0.1, 2),
            'depreciation_method' => 'straight_line',
            'useful_life_years' => fake()->numberBetween(3, 10),
            'status' => 'in_use',
        ];
    }

    /**
     * An asset written down by a fixed percentage of its remaining value.
     */
    public function reducingBalance(float $rate = 20): static
    {
        return $this->state(fn () => [
            'depreciation_method' => 'reducing_balance',
            'depreciation_rate' => $rate,
            'useful_life_years' => null,
        ]);
    }

    /**
     * Land and the like — held at cost, never written down.
     */
    public function nonDepreciating(): static
    {
        return $this->state(fn () => [
            'depreciation_method' => 'none',
            'useful_life_years' => null,
            'salvage_value' => 0,
        ]);
    }
}
