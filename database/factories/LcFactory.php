<?php

namespace Database\Factories;

use App\Models\Lc;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lc>
 */
class LcFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = fake()->randomFloat(2, 5000, 20000);
        $received = $invoice - fake()->randomElement([300, 330]);

        return [
            'order_id' => Order::factory(),
            'pi_date' => now()->toDateString(),
            'pi_no' => 'RTCHW-'.fake()->unique()->numberBetween(1, 999).'/2026',
            'lc_number' => (string) fake()->numerify('93261#####'),
            'opening_bank' => 'Janata Bank',
            'container_no' => strtoupper(fake()->bothify('????#######')),
            'commodity' => fake()->word(),
            'invoice_amount' => $invoice,
            'currency' => 'USD',
            'net_amount_received' => $received,
            'bank_charges' => round($invoice - $received, 2),
            'lc_status' => 'draft',
        ];
    }
}
