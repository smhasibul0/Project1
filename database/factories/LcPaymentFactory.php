<?php

namespace Database\Factories;

use App\Models\Lc;
use App\Models\LcPayment;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LcPayment>
 */
class LcPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $usd = fake()->randomFloat(2, 1000, 10000);

        return [
            'lc_id' => Lc::factory(),
            'payment_account_id' => PaymentAccount::factory(),
            'paid_on' => now()->toDateString(),
            'usd_amount' => $usd,
            'bank_rate' => 123.1,
            'bdt_amount' => LcPayment::takaFor($usd, 123.1),
        ];
    }
}
