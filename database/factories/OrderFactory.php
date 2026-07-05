<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Contact::factory()->customer(),
            'order_date' => now()->toDateString(),
            'goods_status' => 'pending',
            'delivery_status' => 'pending',
        ];
    }
}
