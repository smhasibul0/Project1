<?php

use App\Models\Contact;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create(['name' => 'Karim', 'business_name' => 'Karim Traders', 'shipping_mark' => 'KT-01']);
});

test('a customer\'s shipments list their orders with where each one is and what is owed', function () {
    $onTheWay = Order::factory()->create([
        'customer_id' => $this->customer->id, 'shipment_no' => 'SHP-77', 'goods_status' => 'shipped',
        'total_cbm' => 3.5, 'total_amount' => 35000, 'received_amount' => 10000, 'due_amount' => 25000,
    ]);
    $onTheWay->items()->create(['item_description' => 'Toys', 'package_quantity' => 12, 'cbm' => 3.5]);
    Order::factory()->create([
        'customer_id' => $this->customer->id, 'goods_status' => 'delivered', 'delivered_date' => '2026-10-01',
        'total_cbm' => 2, 'total_amount' => 20000, 'received_amount' => 20000, 'due_amount' => 0,
    ]);
    $someoneElse = Order::factory()->create(['shipment_no' => 'OTHER-1']);

    $response = $this->actingAs($this->user)->get(route('customer.shipments', $this->customer->id));

    $response->assertOk()
        ->assertSee('Shipments — Karim Traders')
        ->assertSee('KT-01')
        ->assertSee('SHP-77')
        ->assertSee('Shipped')
        ->assertSee('01 Oct 2026')
        ->assertSee(route('order.track', $onTheWay->track_token))
        ->assertDontSee('OTHER-1')
        ->assertDontSee($someoneElse->order_no);

    expect($response->viewData('summary'))->toBe([
        'shipments' => 2, 'on_the_way' => 1, 'delivered' => 1, 'cbm' => 5.5, 'billed' => 55000.0, 'due' => 25000.0,
    ]);
});

test('the customer list opens shipments instead of a coming-soon notice', function () {
    $this->actingAs($this->user)->get(route('customers.index'))
        ->assertOk()
        ->assertSee(route('customer.shipments', $this->customer->id))
        ->assertDontSee('data-feature="Shipments"', false);
});

test('shipments need permission to see orders', function () {
    $this->actingAs(userWithPermissions(['customers.view']));

    $this->get(route('customer.shipments', $this->customer->id))->assertForbidden();
    $this->get(route('customers.index'))->assertOk()->assertDontSee(route('customer.shipments', $this->customer->id));
});
