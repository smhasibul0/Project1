<?php

namespace Database\Factories;

use App\Models\HsCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HsCode>
 */
class HsCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $digits = fake()->unique()->numerify('########');

        return [
            'code' => substr($digits, 0, 4).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 2),
            'description' => fake()->sentence(),
            'statistical_unit' => fake()->randomElement(['kg', 'u', 'l']),
            'cd_rate' => 5,
            'sd_rate' => 0,
            'vat_rate' => 15,
            'ait_rate' => 5,
            'rd_rate' => 0,
            'at_rate' => 7.5,
            'is_active' => true,
        ];
    }
}
