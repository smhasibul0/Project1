<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
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
            'query_received_date' => now()->toDateString(),
            'status' => 'draft',
            'submitted_to_customer' => false,
        ];
    }
}
