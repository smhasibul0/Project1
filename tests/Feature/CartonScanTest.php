<?php

use App\Models\Contact;
use App\Models\Container;
use App\Models\Order;
use App\Models\OrderScan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A warehouse hand: no admin panel, but allowed to scan cartons through.
 */
function scanningUser(): User
{
    $permission = Permission::firstOrCreate(['key' => 'orders.scan'], ['name' => 'Scan cartons', 'group' => 'Orders']);
    $role = Role::firstOrCreate(['slug' => 'warehouse'], ['name' => 'Warehouse', 'is_system' => true]);
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * An order of 30 cartons with a shipping mark, ready to be scanned along.
 */
function scannableOrder(array $overrides = []): Order
{
    $customer = Contact::factory()->customer()->create(['shipping_mark' => 'RTC/MOM']);
    $order = Order::create(array_merge([
        'customer_id' => $customer->id,
        'order_date' => '2026-08-01',
        'shipping_mark' => 'RTC/MOM',
        'goods_status' => 'pending',
        'delivery_status' => 'pending',
    ], $overrides));

    $order->items()->create([
        'item_description' => 'Hand pumps',
        'quantity' => 300,
        'package_quantity' => 30,
        'cbm' => 8,
        'net_weight' => 400,
        'declared_value' => 5000,
    ]);

    return $order->load('items');
}

/**
 * Read a carton's QR the way the scanner does, and return the key that reading
 * it hands out. Recording is only possible with one.
 */
function decodeCarton(Order $order, ?User $user = null): string
{
    return test()->actingAs($user ?: scanningUser())
        ->getJson(route('scan.lookup', $order->track_token))
        ->assertOk()
        ->json('scan_key');
}

test('a stranger scanning the QR sees the timeline and no scan panel', function () {
    $order = scannableOrder(['goods_status' => 'shipped']);
    $order->logStatus('shipped', 'On the vessel');

    $this->get(route('order.track', $order->track_token))
        ->assertOk()
        ->assertSee($order->order_no)
        ->assertSee('On the vessel')
        ->assertDontSee('Scan a carton to record')
        ->assertDontSee('carton(s) expected');
});

test('a customer signed into the portal still only sees the timeline', function () {
    $order = scannableOrder();
    $role = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);
    $customerUser = User::factory()->create(['role_id' => $role->id, 'contact_id' => $order->customer_id]);

    $this->actingAs($customerUser)->get(route('order.track', $order->track_token))
        ->assertOk()
        ->assertDontSee('Scan a carton to record');
});

test('staff opening the link see the carton counts and a way to the scanner', function () {
    $order = scannableOrder();

    $this->actingAs(scanningUser())->get(route('order.track', $order->track_token))
        ->assertOk()
        ->assertSee('Scan a carton to record')
        ->assertSee('RTC/MOM')
        ->assertSee('30 carton(s) expected');
});

test('nothing can be recorded without reading the carton first', function () {
    $order = scannableOrder();

    // No key at all: the form was never handed one, so it is rejected outright.
    $this->actingAs(scanningUser())->post(route('order.scan', $order->track_token), [
        'stage' => 'china_warehouse',
        'cartons' => 30,
    ])->assertSessionHasErrors('scan_key');

    // A made-up key gets nowhere either.
    $this->actingAs(scanningUser())->post(route('order.scan', $order->track_token), [
        'scan_key' => str_repeat('k', 40),
        'stage' => 'china_warehouse',
        'cartons' => 30,
    ])->assertRedirect(route('scan.index'))->assertSessionHas('error');

    expect(OrderScan::count())->toBe(0);
});

test('a key is good for one recording only', function () {
    $order = scannableOrder();
    $user = scanningUser();
    $key = decodeCarton($order, $user);

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => $key, 'stage' => 'china_warehouse', 'cartons' => 10,
    ]);

    // Reusing it would let one read of the code be counted twice.
    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => $key, 'stage' => 'china_warehouse', 'cartons' => 10,
    ])->assertSessionHas('error');

    expect(OrderScan::count())->toBe(1);
});

test('reading a carton needs the scan permission', function () {
    $order = scannableOrder();
    $role = Role::create(['slug' => 'accountant', 'name' => 'Accountant']);

    $this->actingAs(User::factory()->create(['role_id' => $role->id]))
        ->getJson(route('scan.lookup', $order->track_token))
        ->assertForbidden();

    $this->actingAs(scanningUser())
        ->getJson(route('scan.lookup', str_repeat('z', 32)))
        ->assertNotFound();
});

test('the scanner page opens for staff', function () {
    $this->actingAs(scanningUser())->get(route('scan.index'))
        ->assertOk()
        ->assertSee('Scan a carton')
        ->assertSee('html5-qrcode.min.js', false);
});

test('scanning cartons in at the China warehouse moves the order along', function () {
    $order = scannableOrder();

    $user = scanningUser();

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'china_warehouse',
        'cartons' => 18,
        'note' => 'First lorry',
    ])->assertRedirect(route('scan.index'));

    $order->refresh()->load('scans');
    expect($order->goods_status)->toBe('at_china_warehouse');
    expect($order->cartonsScannedAt('china_warehouse'))->toEqual(18.0);

    // The rest turn up later, read again, and add to the count.
    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'china_warehouse',
        'cartons' => 12,
    ]);

    $order->refresh()->load('scans');
    expect($order->cartonsScannedAt('china_warehouse'))->toEqual(30.0);
    expect($order->scans)->toHaveCount(2);

    // Every scan is written to the timeline the customer reads.
    expect($order->tracking()->latest('id')->first()->note)->toContain('30 of 30');
});

test('loading cartons into a container puts the order in it and splits its cost', function () {
    $order = scannableOrder(['goods_status' => 'at_china_warehouse']);
    $container = Container::factory()->create(['status' => 'booked', 'allocation_basis' => 'cbm']);
    $container->costs()->create(['title' => 'Ocean freight', 'amount' => 900, 'cost_date' => '2026-08-10']);

    $user = scanningUser();

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'container_loaded',
        'cartons' => 30,
        'container_id' => $container->id,
    ])->assertRedirect();

    $order->refresh();
    expect($order->goods_status)->toBe('shipped');
    expect($order->containers)->toHaveCount(1);
    expect((float) $order->containers->first()->pivot->ctn)->toEqual(30.0);
    // Sole order in the container, so it carries the whole cost.
    expect($order->container_cost)->toEqual('900.00');
});

test('the container step insists on a container', function () {
    $order = scannableOrder();

    $user = scanningUser();

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'container_loaded',
        'cartons' => 30,
    ])->assertSessionHasErrors('container_id');

    expect(OrderScan::count())->toBe(0);
});

test('scanning at the port stamps the arrival date', function () {
    $order = scannableOrder(['goods_status' => 'shipped']);

    $user = scanningUser();

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'at_port',
        'cartons' => 30,
    ]);

    $order->refresh();
    expect($order->goods_status)->toBe('at_port');
    expect($order->port_arrival_date->toDateString())->toBe(now()->toDateString());
});

test('scanning into the BD warehouse receives the goods into inventory', function () {
    $warehouse = Warehouse::factory()->create();
    $order = scannableOrder(['goods_status' => 'at_port']);

    $user = scanningUser();

    $this->actingAs($user)->post(route('order.scan', $order->track_token), [
        'scan_key' => decodeCarton($order, $user),
        'stage' => 'bd_warehouse',
        'cartons' => 30,
        'warehouse_id' => $warehouse->id,
    ]);

    $order->refresh();
    expect($order->goods_status)->toBe('at_bd_warehouse');
    expect($order->warehouse_id)->toBe($warehouse->id);
    expect(WarehouseStock::where('order_id', $order->id)->count())->toBe(1);
    expect((float) WarehouseStock::where('order_id', $order->id)->value('received_qty'))->toEqual(300.0);
});

test('someone without the scan permission cannot record a scan', function () {
    $order = scannableOrder();
    $role = Role::create(['slug' => 'accountant', 'name' => 'Accountant']);
    $accountant = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($accountant)->post(route('order.scan', $order->track_token), [
        'stage' => 'china_warehouse',
        'cartons' => 5,
    ])->assertForbidden();

    expect(OrderScan::count())->toBe(0);
});

test('a stranger with no login cannot record a scan', function () {
    $order = scannableOrder();

    $this->post(route('order.scan', $order->track_token), [
        'stage' => 'china_warehouse',
        'cartons' => 5,
    ])->assertRedirect(route('login'));

    expect(OrderScan::count())->toBe(0);
});

test('a scan cannot be aimed at another order by editing the token', function () {
    $order = scannableOrder();

    $this->actingAs(scanningUser())->post('/track/'.str_repeat('z', 32).'/scan', [
        'stage' => 'china_warehouse',
        'cartons' => 5,
    ])->assertNotFound();

    expect(OrderScan::count())->toBe(0);
});

test('the tracking page shows the carton QR to whoever opens it', function () {
    $order = scannableOrder();

    // The customer prints it from their own link — nothing has to be emailed over.
    $this->get(route('order.track', $order->track_token))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('RTC/MOM')
        ->assertSee('Print label');
});

test('the order page shows the QR in its tracking section', function () {
    $order = scannableOrder();

    $this->actingAs(adminUser())->get(route('order.show', $order->id))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('Print carton labels');
});

test('the printable label carries the QR, the shipping mark and the tracking link', function () {
    $order = scannableOrder();

    $response = $this->actingAs(adminUser())->get(route('order.label', $order->id));

    $response->assertOk()
        ->assertSee('RTC/MOM')
        ->assertSee($order->order_no)
        ->assertSee('Scan to track this shipment')
        ->assertSee('<svg', false);          // the QR is rendered server-side

    // One label per carton by default.
    $response->assertSee('value="30"', false);
});
