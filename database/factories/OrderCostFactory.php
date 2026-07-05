<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCost>
 */
class OrderCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'title' => fake()->randomElement(['Freight charge', 'Customs duty', 'Local delivery', 'C&F service']),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'cost_date' => now()->toDateString(),
        ];
    }
}
