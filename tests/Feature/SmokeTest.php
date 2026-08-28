<?php

use App\Models\AccountType;
use App\Models\Container;
use App\Models\Lc;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

/**
 * Every no-parameter admin page should render without a runtime error.
 */
dataset('pages', [
    'customers.index', 'customer.groups',
    'hs.codes', 'warehouses.index',
    'quotations.index', 'quotations.create', 'transportation.modes', 'packing.types',
    'orders.index', 'orders.create',
    'lc.index', 'lc.create',
    'container.index', 'container.create',
    'cost.categories',
    'payment.accounts',
    'reports.profit-loss', 'reports.receivables', 'reports.balance-sheet', 'reports.cash-flow',
    'users.index', 'roles.index',
    'settings.company',
]);

test('admin page renders', function (string $routeName) {
    $this->actingAs($this->user)->get(route($routeName))->assertOk();
})->with('pages');

test('the public track page renders', function () {
    $this->get(route('order.track'))->assertOk();
});

test('detail, edit and print pages render for a seeded record', function () {
    $quotation = Quotation::factory()->create();
    $order = Order::factory()->create();
    $lc = Lc::factory()->create(['order_id' => $order->id]);
    $container = Container::factory()->create();
    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash in Hand', 'account_type_id' => $type->id, 'balance' => 0, 'is_active' => true]);

    $get = fn (string $url) => $this->actingAs($this->user)->get($url)->assertOk();

    $get(route('quotation.show', $quotation->id));
    $get(route('quotation.edit', $quotation->id));
    $get(route('order.show', $order->id));
    $get(route('order.edit', $order->id));
    $get(route('order.invoice', $order->id));
    $get(route('lc.show', $lc->id));
    $get(route('lc.edit', $lc->id));
    $get(route('container.show', $container->id));
    $get(route('container.edit', $container->id));
    $get(route('container.packing.list', $container->id));
    $get(route('container.loading.list', $container->id));
    $get(route('payment.account.book', $account->id));
});
