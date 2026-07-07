<?php

use App\Models\Contact;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the dashboard renders with no data', function () {
    $this->actingAs(adminUser())->get(route('dashboard'))->assertOk()->assertSee('Dashboard');
});

test('the dashboard renders with orders and metrics', function () {
    $customer = Contact::factory()->customer()->create(['name' => 'Acme Co']);
    Order::factory()->count(3)->create([
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'total_amount' => 1000,
        'profit' => 300,
        'due_amount' => 200,
        'goods_status' => 'shipped',
    ]);

    $this->actingAs(adminUser())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Revenue Trend')
        ->assertSee('Order Pipeline')
        ->assertSee('Recent Orders')
        ->assertSee('Top Customers')
        ->assertSee('Acme Co');
});
