<?php

namespace Database\Factories;

use App\Models\DollarTransaction;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A dollar movement on its own. It doesn't touch the account's balances — use
 * App\Support\DollarLedger for that.
 *
 * @extends Factory<DollarTransaction>
 */
class DollarTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $usd = fake()->randomFloat(2, 100, 5000);

        return [
            'payment_account_id' => PaymentAccount::factory(),
            'entry_date' => now()->toDateString(),
            'direction' => 'in',
            'source' => 'deposit',
            'usd_amount' => $usd,
            'rate' => 122.5,
            'bdt_amount' => round($usd * 122.5, 2),
            'description' => 'Dollars deposited',
        ];
    }
}
