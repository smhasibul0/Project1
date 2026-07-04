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
            'type' => 'customer',
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
     * A supplier contact.
     */
    public function supplier(): static
    {
        return $this->state(fn () => ['type' => 'supplier']);
    }

    /**
     * A customer contact.
     */
    public function customer(): static
    {
        return $this->state(fn () => ['type' => 'customer']);
    }
}
