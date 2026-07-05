<?php

use App\Models\AccountType;
use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderExpense;
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

test('an order is created with items, expenses and computed financials', function () {
    $response = $this->actingAs($this->user)->post(route('order.store'), [
        'order_date' => '2026-07-05',
        'customer_id' => $this->customer->id,
        'goods_handover_date' => '2026-07-01',
        'delivered_date' => '2026-07-11',
        'discount_type' => 'fixed',
        'discount_value' => 200,
        'items' => [
            ['item_description' => 'Toys', 'quantity' => 10, 'supplier_asking_price' => 100, 'our_asking_price' => 150],
            ['item_description' => 'Bags', 'quantity' => 5, 'supplier_asking_price' => 200, 'our_asking_price' => 300],
        ],
        'expenses' => [
            ['title' => 'Freight', 'amount' => 100],
            ['title' => 'Customs', 'amount' => 50],
        ],
        'payments' => [
            ['amount' => 600, 'payment_date' => '2026-07-05', 'method' => 'Cash'],
            ['amount' => 400, 'payment_date' => '2026-07-06', 'method' => 'Bank Transfer'],
        ],
    ]);

    $response->assertRedirect(route('orders.index'));

    $order = Order::with(['items', 'expenses', 'payments'])->firstOrFail();
    expect($order->order_no)->toBe('OR0001');
    expect($order->items)->toHaveCount(2);
    expect($order->expenses)->toHaveCount(2);
    expect($order->payments)->toHaveCount(2);

    // subtotal 3000, discount 200 -> total 2800 ; cost 2000 ; expenses 150 ; profit 650
    expect($order->subtotal)->toEqual('3000.00');
    expect($order->total_amount)->toEqual('2800.00');
    expect($order->total_expense)->toEqual('150.00');
    expect($order->profit)->toEqual('650.00');
    // two payments 600+400 = 1000 received -> due 1800, partial
    expect($order->received_amount)->toEqual('1000.00');
    expect($order->due_amount)->toEqual('1800.00');
    expect($order->payment_status)->toBe('partial');
    expect($order->total_delivery_days)->toBe(10);
    expect($order->items->first()->line_total)->toEqual('1500.00');
});

test('an order can be created from an accepted quotation', function () {
    // Build a quotation with two items via the controller.
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'status' => 'accepted',
        'items' => [
            ['package_quantity' => 4, 'supplier_asking_price' => 50, 'our_asking_price' => 80, 'hs_code' => '9503009'],
            ['package_quantity' => 2, 'supplier_asking_price' => 100, 'our_asking_price' => 160],
        ],
    ]);
    $quotation = Quotation::firstOrFail();

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))
        ->assertRedirect(route('order.edit', 1));

    $order = Order::with('items')->firstOrFail();
    expect($order->quotation_id)->toBe($quotation->id);
    expect($order->customer_id)->toBe($this->customer->id);
    expect($order->items)->toHaveCount(2);
    // 80*4 + 160*2 = 640
    expect($order->subtotal)->toEqual('640.00');

    // Quotation is marked converted.
    expect($quotation->fresh()->status)->toBe('converted');
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
        'items' => [['quantity' => 2, 'supplier_asking_price' => 60, 'our_asking_price' => 100]],
    ]);
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->put(route('order.update', $order->id), [
        'customer_id' => $this->customer->id,
        'goods_status' => 'shipped',
        'payments' => [['amount' => 900, 'method' => 'Cash']],
        'items' => [['quantity' => 3, 'supplier_asking_price' => 150, 'our_asking_price' => 300]],
    ]);

    $order->refresh()->load('items');
    expect($order->items)->toHaveCount(1);
    expect($order->goods_status)->toBe('shipped');
    expect($order->total_amount)->toEqual('900.00');   // 300*3
    expect($order->payment_status)->toBe('paid');       // received 900 == total
    expect($order->profit)->toEqual('450.00');          // 900 - 150*3
});

test('an order can be deleted with items and expenses', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
        'expenses' => [['title' => 'X', 'amount' => 2]],
    ]);
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->delete(route('order.delete', $order->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
    expect(OrderExpense::count())->toBe(0);
});

test('the Pay quick action records a payment and deposits into the chosen account', function () {
    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['quantity' => 5, 'our_asking_price' => 100, 'supplier_asking_price' => 60]],
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
