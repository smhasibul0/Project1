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

test('the container pages and generated lists load', function () {
    $container = Container::factory()->create();
    $order = Order::factory()->create();
    $order->items()->create(['item_description' => 'Toys', 'quantity' => 5, 'package_quantity' => 3, 'cbm' => 1.2, 'our_asking_price' => 100, 'supplier_asking_price' => 60, 'line_total' => 500]);
    $container->orders()->attach($order->id, ['ctn' => 3, 'weight' => 100, 'cbm' => 1.2]);

    $this->actingAs($this->user)->get(route('container.index'))->assertOk();
    $this->actingAs($this->user)->get(route('container.create'))->assertOk()->assertSee('Add Container');
    $this->actingAs($this->user)->get(route('container.show', $container->id))->assertOk();
    $this->actingAs($this->user)->get(route('container.packing.list', $container->id))->assertOk()->assertSee('PACKING LIST');
    $this->actingAs($this->user)->get(route('container.loading.list', $container->id))->assertOk()->assertSee('LOADING LIST');
});
