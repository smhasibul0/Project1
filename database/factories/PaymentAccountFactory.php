<?php

namespace Database\Factories;

use App\Models\AccountType;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Account',
            'account_type_id' => AccountType::factory(),
            'account_number' => fake()->numerify('##########'),
            'balance' => 0,
            'is_active' => true,
        ];
    }
}
