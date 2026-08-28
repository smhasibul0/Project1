<?php

use App\Models\AccountType;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAccount;
use App\Models\Quotation;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create();
});

test('an order is created with items, payments and computed financials', function () {
    $response = $this->actingAs($this->user)->post(route('order.store'), [
        'order_date' => '2026-07-05',
        'customer_id' => $this->customer->id,
        'goods_handover_date' => '2026-07-01',
        'delivered_date' => '2026-07-11',
        'discount_type' => 'fixed',
        'discount_value' => 200,
        'cost_rate_per_cbm' => 50,
        'sell_rate_per_cbm' => 150,
        'items' => [
            ['item_description' => 'Toys', 'quantity' => 10, 'cbm' => 12, 'declared_value' => 1000],
            ['item_description' => 'Bags', 'quantity' => 5, 'cbm' => 8, 'declared_value' => 500],
        ],
        'payments' => [
            ['amount' => 600, 'payment_date' => '2026-07-05', 'method' => 'Cash'],
            ['amount' => 400, 'payment_date' => '2026-07-06', 'method' => 'Bank Transfer'],
        ],
    ]);

    $response->assertRedirect(route('orders.index'));

    $order = Order::with(['items', 'payments'])->firstOrFail();
    expect($order->order_no)->toBe('OR0001');
    expect($order->items)->toHaveCount(2);
    expect($order->payments)->toHaveCount(2);

    // 20 CBM at 150 = 3000, less 200 discount = 2800 ; freight 20 x 50 = 1000 ; profit 1800
    expect($order->total_cbm)->toEqual('20.0000');
    expect($order->subtotal)->toEqual('3000.00');
    expect($order->total_amount)->toEqual('2800.00');
    expect($order->freight_cost)->toEqual('1000.00');
    expect($order->total_expense)->toEqual('0.00');
    expect($order->profit)->toEqual('1800.00');
    // two payments 600+400 = 1000 received -> due 1800, partial
    expect($order->received_amount)->toEqual('1000.00');
    expect($order->due_amount)->toEqual('1800.00');
    expect($order->payment_status)->toBe('partial');
    expect($order->total_delivery_days)->toBe(10);
    expect($order->items->first()->line_total)->toEqual('1800.00'); // 12 CBM at 150
});

test('an order can be created from an accepted quotation', function () {
    // Build a quotation with two items via the controller.
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'status' => 'accepted',
        'packing_list' => fakePackingList(),
        'country_of_loading' => 'China',
        'sell_rate_per_cbm' => 80,
        'items' => [
            ['package_quantity' => 4, 'cbm' => 6, 'declared_value' => 200, 'hs_code' => '9503.00.90'],
            ['package_quantity' => 2, 'cbm' => 2, 'declared_value' => 200],
        ],
    ]);
    $quotation = Quotation::firstOrFail();

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))
        ->assertRedirect(route('order.edit', 1));

    $order = Order::with('items')->firstOrFail();
    expect($order->quotation_id)->toBe($quotation->id);
    expect($order->customer_id)->toBe($this->customer->id);
    // The quoted shipment details come across with it.
    expect($order->country_of_loading)->toBe('China');
    expect($order->items)->toHaveCount(2);
    // 8 CBM at the quoted 80/CBM
    expect($order->subtotal)->toEqual('640.00');
    expect($order->items->first()->hs_code)->toBe('9503.00.90');

    // Quotation is marked converted.
    expect($quotation->fresh()->status)->toBe('converted');
});

test('a quotation can be converted whatever status it is in', function () {
    $quotation = Quotation::factory()->create(['status' => 'draft', 'customer_id' => $this->customer->id]);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))
        ->assertRedirect(route('order.edit', 1))
        ->assertSessionHas('success');

    expect(Order::where('quotation_id', $quotation->id)->exists())->toBeTrue();
    expect($quotation->fresh()->status)->toBe('converted');
});

test('converting the same quotation twice does not create a second order', function () {
    $quotation = Quotation::factory()->create(['status' => 'accepted', 'customer_id' => $this->customer->id]);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id));
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))
        ->assertRedirect(route('order.show', $order->id))
        ->assertSessionHas('error');

    expect(Order::count())->toBe(1);
});

test('the quotation list offers Convert to Order until the quotation is converted', function () {
    Quotation::factory()->create(['status' => 'quoted', 'customer_id' => $this->customer->id]);

    $this->actingAs($this->user)->get(route('quotations.index'))
        ->assertOk()
        ->assertSee('Convert to Order');

    Quotation::query()->update(['status' => 'converted']);

    $this->actingAs($this->user)->get(route('quotations.index'))
        ->assertOk()
        ->assertDontSee('Convert to Order')
        ->assertSee('Already converted');
});

test('an order requires at least one item', function () {
    $response = $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
    ]);

    $response->assertSessionHasErrors('items');
    expect(Order::count())->toBe(0);
});

test('updating an order replaces items and recomputes', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'sell_rate_per_cbm' => 100,
        'items' => [['quantity' => 2, 'cbm' => 2, 'declared_value' => 60]],
    ]);
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->put(route('order.update', $order->id), [
        'customer_id' => $this->customer->id,
        'goods_status' => 'shipped',
        'cost_rate_per_cbm' => 150,
        'sell_rate_per_cbm' => 300,
        'payments' => [['amount' => 900, 'method' => 'Cash']],
        'items' => [['quantity' => 3, 'cbm' => 3, 'declared_value' => 150]],
    ]);

    $order->refresh()->load('items');
    expect($order->items)->toHaveCount(1);
    expect($order->goods_status)->toBe('shipped');
    expect($order->total_amount)->toEqual('900.00');   // 3 CBM at 300
    expect($order->payment_status)->toBe('paid');       // received 900 == total
    expect($order->profit)->toEqual('450.00');          // 900 - freight 3 x 150
});

test('an order can be deleted with its items', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'sell_rate_per_cbm' => 10,
        'items' => [['quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
    ]);
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->delete(route('order.delete', $order->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
});

test('the Pay quick action records a payment and deposits into the chosen account', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'sell_rate_per_cbm' => 100,
        'items' => [['quantity' => 5, 'cbm' => 5, 'declared_value' => 60]],
    ]);
    $order = Order::firstOrFail(); // total 500, due 500

    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash in Hand', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);

    $this->actingAs($this->user)->post(route('order.payment', $order->id), [
        'amount' => 300,
        'payment_date' => '2026-07-05',
        'method' => 'Cash',
        'payment_account_id' => $account->id,
        'note' => 'Advance',
    ])->assertSessionHas('success');

    $order->refresh();
    expect($order->payments()->count())->toBe(1);
    expect($order->received_amount)->toEqual('300.00');
    expect($order->due_amount)->toEqual('200.00');
    expect($order->payment_status)->toBe('partial');

    // Deposited into the account ledger.
    expect($account->fresh()->balance)->toEqual('1300.00');
    expect(Transaction::where('source', 'order_payment')->where('credit', 300)->exists())->toBeTrue();
});

test('the order list and create pages load', function () {
    $this->actingAs($this->user)->get(route('orders.index'))->assertOk();
    $this->actingAs($this->user)->get(route('orders.create'))->assertOk()->assertSee('Add Order');
});
