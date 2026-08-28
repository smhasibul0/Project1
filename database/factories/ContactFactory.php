<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->numerify('01#########'),
            'tax_number' => fake()->numerify('TIN#######'),
            'opening_balance' => 0,
            'country' => fake()->country(),
            'is_active' => true,
        ];
    }

    /**
     * Kept so existing tests read clearly — every contact is a customer.
     */
    public function customer(): static
    {
        return $this->state(fn () => []);
    }
}
