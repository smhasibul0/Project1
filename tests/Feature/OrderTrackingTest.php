<?php

use App\Models\Contact;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create();
});

/**
 * Create an order (with its initial tracking entry) and return it.
 */
function makeOrder(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'customer_id' => Contact::factory()->customer()->create()->id,
        'order_date' => '2026-07-01',
        'goods_status' => 'pending',
        'delivery_status' => 'pending',
    ], $overrides));
    $order->logStatus($order->goods_status, 'Order created');

    return $order;
}

test('creating an order via the controller logs an initial tracking entry', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
    ]);

    $order = Order::with('tracking')->firstOrFail();
    expect($order->tracking)->toHaveCount(1);
    expect($order->tracking->first()->status)->toBe('pending');
});

test('updating status logs a tracking row and stamps the BD warehouse date', function () {
    $order = makeOrder();

    $this->actingAs($this->user)->post(route('order.status', $order->id), [
        'goods_status' => 'at_bd_warehouse',
        'note' => 'Received 12 cartons',
    ])->assertSessionHas('success');

    $order->refresh()->load('tracking');
    expect($order->goods_status)->toBe('at_bd_warehouse');
    expect($order->bd_warehouse_date->toDateString())->toBe(now()->toDateString());
    expect($order->tracking)->toHaveCount(2);
    expect($order->tracking->first()->note)->toBe('Received 12 cartons'); // latest first
});

test('marking delivered sets delivery status, date and computes delivery days', function () {
    $order = makeOrder(['goods_handover_date' => '2026-07-01']);

    $this->actingAs($this->user)->post(route('order.status', $order->id), [
        'goods_status' => 'delivered',
    ]);

    $order->refresh();
    expect($order->delivery_status)->toBe('delivered');
    expect($order->delivered_date->toDateString())->toBe(now()->toDateString());
    expect($order->total_delivery_days)->toBe((int) Carbon::parse('2026-07-01')->diffInDays(now()));
});

test('warehouse staff can update status but cannot create or edit orders', function () {
    $perm = Permission::firstOrCreate(['key' => 'orders.update-status'], ['name' => 'Update status', 'group' => 'Orders']);
    $role = Role::create(['name' => 'Warehouse Staff', 'slug' => 'warehouse-staff']);
    $role->permissions()->attach($perm);
    $staff = User::factory()->create(['role_id' => $role->id]);

    $order = makeOrder();

    // Can view + update status.
    $this->actingAs($staff)->get(route('order.show', $order->id))->assertOk();
    $this->actingAs($staff)->post(route('order.status', $order->id), ['goods_status' => 'shipped'])
        ->assertSessionHas('success');

    // Cannot reach full management.
    $this->actingAs($staff)->get(route('orders.create'))->assertForbidden();
    $this->actingAs($staff)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['quantity' => 1, 'cbm' => 1]],
    ])->assertForbidden();

    expect($order->fresh()->goods_status)->toBe('shipped');
});

test('the public tracking page shows an order status and timeline without login', function () {
    $order = makeOrder(['goods_status' => 'shipped']);
    $order->logStatus('shipped', 'On the vessel');

    $this->get(route('order.track', $order->track_token))
        ->assertOk()
        ->assertSee($order->order_no)
        ->assertSee('Shipped')
        ->assertSee('On the vessel');
});

test('every order gets its own random tracking token', function () {
    $first = makeOrder();
    $second = makeOrder();

    expect($first->track_token)->toHaveLength(32);
    expect($first->track_token)->not->toBe($second->track_token);
    // Nothing about the token follows from the order number.
    expect($first->track_token)->not->toContain($first->order_no);
});

test('a tracking link cannot be edited into another order', function () {
    $mine = makeOrder(['goods_status' => 'shipped']);
    $theirs = makeOrder(['goods_status' => 'at_port']);

    // My link shows my shipment and says nothing about anyone else's.
    $this->get(route('order.track', $mine->track_token))
        ->assertOk()
        ->assertSee($mine->order_no)
        ->assertDontSee($theirs->order_no);

    // The order number is not a way in, and neither is a made-up token.
    $this->get('/track/'.$theirs->order_no)->assertNotFound();
    $this->get('/track/'.str_repeat('a', 32))->assertNotFound();
});
