<?php

use App\Models\AccountType;
use App\Models\CostCategory;
use App\Models\Order;
use App\Models\OrderCost;
use App\Models\PaymentAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->order = Order::factory()->create(['cost_rate_per_cbm' => 60, 'sell_rate_per_cbm' => 100]);
    // One item: 5 CBM charged at 100 = 500, freight 5 x 60 = 300 -> profit before costs 200.
    $this->order->items()->create([
        'item_description' => 'Toys',
        'quantity' => 5,
        'cbm' => 5,
        'declared_value' => 300,
        'line_total' => 500,
    ]);
});

/**
 * An accountant-style user: has costs.manage but not orders.manage.
 */
function accountantUser(): User
{
    $permission = Permission::firstOrCreate(['key' => 'costs.manage'], ['name' => 'Manage order costs', 'group' => 'Order Costs']);
    $role = Role::firstOrCreate(['slug' => 'accountant'], ['name' => 'Accountant']);
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    return User::factory()->create(['role_id' => $role->id]);
}

test('a cost is recorded against an order and folds into profit', function () {
    $this->actingAs($this->user)->post(route('order.cost.store', $this->order->id), [
        'title' => 'Sea freight',
        'amount' => 100,
    ])->assertRedirect()->assertSessionHas('success');

    $this->order->refresh();
    expect($this->order->costs()->count())->toBe(1);
    expect($this->order->total_expense)->toEqual('100.00');
    // total 500 - supplier 300 - cost 100 = 100
    expect($this->order->profit)->toEqual('100.00');
    // No ledger entry when no account is chosen.
    expect(Transaction::where('source', 'order_cost')->exists())->toBeFalse();
});

test('a cost paid from an account debits the account ledger', function () {
    $type = AccountType::create(['name' => 'Bank']);
    $account = PaymentAccount::create(['name' => 'Main Bank', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);
    $category = CostCategory::factory()->create();

    $this->actingAs($this->user)->post(route('order.cost.store', $this->order->id), [
        'cost_category_id' => $category->id,
        'title' => 'Customs duty',
        'amount' => 200,
        'payment_account_id' => $account->id,
    ])->assertSessionHas('success');

    expect($account->fresh()->balance)->toEqual('800.00');

    $tx = Transaction::where('source', 'order_cost')->firstOrFail();
    expect($tx->debit)->toEqual('200.00');
    expect($tx->credit)->toEqual('0.00');
    expect($tx->transactionable_type)->toBe(OrderCost::class);
});

test('deleting a paid cost reverses the account ledger', function () {
    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);

    $this->actingAs($this->user)->post(route('order.cost.store', $this->order->id), [
        'title' => 'Local transport',
        'amount' => 200,
        'payment_account_id' => $account->id,
    ]);
    expect($account->fresh()->balance)->toEqual('800.00');

    $cost = OrderCost::firstOrFail();
    $this->actingAs($this->user)->delete(route('order.cost.delete', [$this->order->id, $cost->id]))
        ->assertSessionHas('success');

    expect($account->fresh()->balance)->toEqual('1000.00');
    expect(Transaction::where('source', 'order_cost')->exists())->toBeFalse();
    expect(OrderCost::count())->toBe(0);
});

test('a cost requires a title and an amount', function () {
    $this->actingAs($this->user)->post(route('order.cost.store', $this->order->id), [
        'title' => '',
    ])->assertSessionHasErrors(['title', 'amount']);

    expect(OrderCost::count())->toBe(0);
});

test('cost categories can be managed', function () {
    $this->actingAs($this->user)->post(route('cost.category.store'), ['name' => 'Freight'])
        ->assertSessionHas('success');
    $category = CostCategory::firstOrFail();
    expect($category->name)->toBe('Freight');

    $this->actingAs($this->user)->put(route('cost.category.update', $category->id), ['name' => 'Freight & Handling'])
        ->assertSessionHas('success');
    expect($category->fresh()->name)->toBe('Freight & Handling');

    $this->actingAs($this->user)->delete(route('cost.category.delete', $category->id))
        ->assertSessionHas('success');
    expect(CostCategory::count())->toBe(0);
});

test('an accountant with costs.manage can view an order and add a cost', function () {
    $accountant = accountantUser();

    $this->actingAs($accountant)->get(route('order.show', $this->order->id))->assertOk();

    $this->actingAs($accountant)->post(route('order.cost.store', $this->order->id), [
        'title' => 'C&F service',
        'amount' => 150,
    ])->assertSessionHas('success');

    expect($this->order->costs()->count())->toBe(1);
});
