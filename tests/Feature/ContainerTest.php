<?php

use App\Models\AccountType;
use App\Models\Container;
use App\Models\ContainerCost;
use App\Models\ContainerDocument;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('a container is created with an auto code', function () {
    $this->actingAs($this->user)->post(route('container.store'), [
        'shipment_no' => '25/2026 (CTG)',
        'container_number' => 'MSKU1234567',
        'transport_mode' => 'Sea Transport',
        'allocation_basis' => 'cbm',
    ])->assertRedirect();

    $container = Container::firstOrFail();
    expect($container->container_code)->toBe('CNT0001');
    expect($container->shipment_no)->toBe('25/2026 (CTG)');
});

test('orders can be assigned to a container with their loaded quantities', function () {
    $container = Container::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($this->user)->post(route('container.order.store', $container->id), [
        'order_id' => $order->id,
        'ctn' => 10,
        'weight' => 500,
        'cbm' => 6,
    ])->assertSessionHas('success');

    $pivot = $container->orders()->first()->pivot;
    expect((float) $pivot->cbm)->toBe(6.0);
    expect((float) $pivot->ctn)->toBe(10.0);
});

test('re-assigning an order updates its loaded quantity instead of duplicating', function () {
    $container = Container::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $order->id, 'cbm' => 6]);
    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $order->id, 'cbm' => 9]);

    expect($container->orders()->count())->toBe(1);
    expect((float) $container->orders()->first()->pivot->cbm)->toBe(9.0);
});

test('container cost is distributed to orders by CBM and folds into profit', function () {
    $container = Container::factory()->create(['allocation_basis' => 'cbm']);
    $a = Order::factory()->create();
    $b = Order::factory()->create();

    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $a->id, 'cbm' => 6]);
    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $b->id, 'cbm' => 4]);

    $this->actingAs($this->user)->post(route('container.cost.store', $container->id), [
        'title' => 'Ocean freight',
        'amount' => 1000,
    ])->assertSessionHas('success');

    // total cbm 10 -> A(6) = 600, B(4) = 400
    expect($a->fresh()->container_cost)->toEqual('600.00');
    expect($b->fresh()->container_cost)->toEqual('400.00');
    // no revenue/costs otherwise -> profit is negative of the allocated share
    expect($a->fresh()->profit)->toEqual('-600.00');
});

test('container cost can be split equally', function () {
    $container = Container::factory()->create(['allocation_basis' => 'equal']);
    $a = Order::factory()->create();
    $b = Order::factory()->create();

    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $a->id, 'cbm' => 6]);
    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $b->id, 'cbm' => 4]);
    $this->actingAs($this->user)->post(route('container.cost.store', $container->id), ['title' => 'Handling', 'amount' => 1000]);

    expect($a->fresh()->container_cost)->toEqual('500.00');
    expect($b->fresh()->container_cost)->toEqual('500.00');
});

test('removing an order re-allocates the remaining orders and clears its share', function () {
    $container = Container::factory()->create(['allocation_basis' => 'cbm']);
    $a = Order::factory()->create();
    $b = Order::factory()->create();

    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $a->id, 'cbm' => 6]);
    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $b->id, 'cbm' => 4]);
    $this->actingAs($this->user)->post(route('container.cost.store', $container->id), ['title' => 'Freight', 'amount' => 1000]);

    $this->actingAs($this->user)->delete(route('container.order.delete', [$container->id, $a->id]))->assertSessionHas('success');

    expect($a->fresh()->container_cost)->toEqual('0.00');
    // b is now the only order -> takes the whole cost
    expect($b->fresh()->container_cost)->toEqual('1000.00');
});

test('a container cost paid from an account debits the ledger and reverses on delete', function () {
    $container = Container::factory()->create();
    $order = Order::factory()->create();
    $this->actingAs($this->user)->post(route('container.order.store', $container->id), ['order_id' => $order->id, 'cbm' => 5]);

    $type = AccountType::create(['name' => 'Bank']);
    $account = PaymentAccount::create(['name' => 'Ops Bank', 'account_type_id' => $type->id, 'balance' => 5000, 'is_active' => true]);

    $this->actingAs($this->user)->post(route('container.cost.store', $container->id), [
        'title' => 'Ocean freight',
        'amount' => 1000,
        'payment_account_id' => $account->id,
    ])->assertSessionHas('success');

    expect($account->fresh()->balance)->toEqual('4000.00');
    $tx = Transaction::where('source', 'container_cost')->firstOrFail();
    expect($tx->debit)->toEqual('1000.00');

    $cost = ContainerCost::firstOrFail();
    $this->actingAs($this->user)->delete(route('container.cost.delete', [$container->id, $cost->id]))->assertSessionHas('success');

    expect($account->fresh()->balance)->toEqual('5000.00');
    expect(Transaction::where('source', 'container_cost')->exists())->toBeFalse();
});

test('a document can be uploaded and deleted', function () {
    $container = Container::factory()->create();

    $this->actingAs($this->user)->post(route('container.document.store', $container->id), [
        'type' => 'Bill of Lading (B/L)',
        'provided_by' => 'RTC',
        'file' => UploadedFile::fake()->create('bl.pdf', 100, 'application/pdf'),
    ])->assertSessionHas('success');

    $doc = ContainerDocument::firstOrFail();
    expect(file_exists(public_path('upload/containers/'.$doc->file)))->toBeTrue();

    $this->actingAs($this->user)->delete(route('container.document.delete', [$container->id, $doc->id]))->assertSessionHas('success');
    expect(ContainerDocument::count())->toBe(0);

    @unlink(public_path('upload/containers/'.$doc->file));
});

test("a container's number and its cost share show on the orders inside it", function () {
    $container = Container::factory()->create([
        'container_number' => 'FFAU7873853',
        'allocation_basis' => 'cbm',
    ]);
    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();
    $container->orders()->attach($orderA->id, ['ctn' => 3, 'weight' => 100, 'cbm' => 6]);
    $container->orders()->attach($orderB->id, ['ctn' => 2, 'weight' => 50, 'cbm' => 2]);

    // Adding a cost to the container splits it across both orders by CBM straight away.
    $this->actingAs($this->user)->post(route('container.cost.store', $container->id), [
        'title' => 'Ocean freight',
        'amount' => 800,
        'cost_date' => '2026-07-05',
    ])->assertSessionHas('success');

    expect($orderA->fresh()->container_cost)->toEqual('600.00');  // 6 of 8 CBM
    expect($orderB->fresh()->container_cost)->toEqual('200.00');  // 2 of 8 CBM

    // The order picks the container number up from the container, no typing needed.
    expect($orderA->fresh()->containerNumbers())->toBe('FFAU7873853');
    expect($orderA->fresh()->load('containers')->shareOfContainerCost($container->fresh()))->toEqual(600.0);

    $this->actingAs($this->user)->get(route('order.show', $orderA->id))
        ->assertOk()
        ->assertSee('FFAU7873853')
        ->assertSee('Container No');

    $this->actingAs($this->user)->get(route('orders.index'))
        ->assertOk()
        ->assertSee('FFAU7873853');
});

test('an order in two containers shows both numbers and the summed share', function () {
    $order = Order::factory()->create();
    $first = Container::factory()->create(['container_number' => 'AAAU1111111', 'allocation_basis' => 'equal']);
    $second = Container::factory()->create(['container_number' => 'BBBU2222222', 'allocation_basis' => 'equal']);

    foreach ([$first, $second] as $container) {
        $container->orders()->attach($order->id, ['ctn' => 1, 'weight' => 10, 'cbm' => 1]);
        $container->costs()->create(['title' => 'Freight', 'amount' => 300, 'cost_date' => '2026-07-05']);
    }

    $order->refresh()->recomputeFinancials();

    expect($order->containerNumbers())->toBe('AAAU1111111, BBBU2222222');
    expect($order->fresh()->container_cost)->toEqual('600.00');
});

test('the container pages and generated lists load', function () {
    $container = Container::factory()->create();
    $order = Order::factory()->create();
    $order->items()->create(['item_description' => 'Toys', 'quantity' => 5, 'package_quantity' => 3, 'cbm' => 1.2, 'declared_value' => 300, 'line_total' => 500]);
    $container->orders()->attach($order->id, ['ctn' => 3, 'weight' => 100, 'cbm' => 1.2]);

    $this->actingAs($this->user)->get(route('container.index'))->assertOk();
    $this->actingAs($this->user)->get(route('container.create'))->assertOk()->assertSee('Add Container');
    $this->actingAs($this->user)->get(route('container.show', $container->id))->assertOk();
    $this->actingAs($this->user)->get(route('container.packing.list', $container->id))->assertOk()->assertSee('PACKING LIST');
    $this->actingAs($this->user)->get(route('container.loading.list', $container->id))->assertOk()->assertSee('LOADING LIST');
});

test('an LCL shipment computes freight from rate x loaded CBM and skips the container number', function () {
    $this->actingAs($this->user)->post(route('container.store'), [
        'shipment_type' => 'lcl',
        'lcl_rate' => 55,
        'allocation_basis' => 'cbm',
    ])->assertRedirect();

    $container = Container::firstOrFail();
    expect($container->shipment_type)->toBe('lcl');
    expect($container->container_number)->toBeNull();

    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();
    $container->orders()->attach($orderA->id, ['cbm' => 6]);
    $container->orders()->attach($orderB->id, ['cbm' => 4]);

    expect($container->cbmTotal())->toEqual(10.0);
    expect($container->lclFreightEstimate())->toEqual(550.0); // 55 x 10 CBM
});

test('an FCL shipment requires a container number and stores its size', function () {
    $this->actingAs($this->user)->post(route('container.store'), [
        'shipment_type' => 'fcl',
        'container_size' => '40HQ',
    ])->assertSessionHasErrors('container_number');

    $this->actingAs($this->user)->post(route('container.store'), [
        'shipment_type' => 'fcl',
        'container_number' => 'MSKU7654321',
        'container_size' => '40HQ',
    ])->assertRedirect();

    $container = Container::firstOrFail();
    expect($container->shipment_type)->toBe('fcl');
    expect($container->container_size)->toBe('40HQ');
    expect($container->lclFreightEstimate())->toEqual(0.0); // LCL-only estimate
});
