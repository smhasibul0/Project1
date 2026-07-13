<?php

use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\StaffSalaryPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseExpense;
use App\Models\WarehouseStaff;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function warehouseUser(Warehouse $warehouse): User
{
    $role = Role::firstOrCreate(['slug' => 'warehouse'], ['name' => 'Warehouse', 'is_system' => true]);

    return User::factory()->create(['role_id' => $role->id, 'warehouse_id' => $warehouse->id]);
}

function orderWithItems(Warehouse $warehouse): Order
{
    $order = Order::factory()->create(['warehouse_id' => $warehouse->id, 'goods_status' => 'at_port']);
    $order->items()->create(['item_description' => 'Toy Car', 'quantity' => 10]);
    $order->items()->create(['item_description' => 'Lamp', 'quantity' => 5]);

    return $order;
}

test('marking an order at BD warehouse receives its items into inventory', function () {
    $warehouse = Warehouse::factory()->create();
    $order = orderWithItems($warehouse);

    $this->actingAs(adminUser())->post(route('order.status', $order->id), [
        'goods_status' => 'at_bd_warehouse',
        'warehouse_id' => $warehouse->id,
    ])->assertRedirect();

    $stocks = WarehouseStock::where('order_id', $order->id)->get();
    expect($stocks)->toHaveCount(2);
    expect($order->fresh()->warehouse_id)->toBe($warehouse->id);
    expect((float) $stocks->firstWhere('item_description', 'Toy Car')->received_qty)->toBe(10.0);
    expect($stocks->firstWhere('item_description', 'Toy Car')->movements()->where('type', 'received')->count())->toBe(1);
});

test('receiving an order into inventory is idempotent', function () {
    $warehouse = Warehouse::factory()->create();
    $order = orderWithItems($warehouse);

    $payload = ['goods_status' => 'at_bd_warehouse', 'warehouse_id' => $warehouse->id];
    $this->actingAs(adminUser())->post(route('order.status', $order->id), $payload);
    $this->actingAs(adminUser())->post(route('order.status', $order->id), $payload);

    expect(WarehouseStock::where('order_id', $order->id)->count())->toBe(2);
});

test('a warehouse user only sees their own warehouse inventory', function () {
    $mine = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    WarehouseStock::create(['warehouse_id' => $mine->id, 'item_description' => 'MyGoods', 'received_qty' => 3]);
    $otherStock = WarehouseStock::create(['warehouse_id' => $other->id, 'item_description' => 'TheirGoods', 'received_qty' => 3]);

    $response = $this->actingAs(warehouseUser($mine))->get(route('warehouse.inventory.index'));
    $response->assertOk()->assertSee('MyGoods')->assertDontSee('TheirGoods');

    $this->actingAs(warehouseUser($mine))->get(route('warehouse.inventory.show', $otherStock->id))->assertNotFound();
});

test('a warehouse user can dispatch stock, reducing on-hand and logging a movement', function () {
    $warehouse = Warehouse::factory()->create();
    $stock = WarehouseStock::create(['warehouse_id' => $warehouse->id, 'item_description' => 'Widget', 'received_qty' => 20]);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.inventory.dispatch', $stock->id), [
        'quantity' => 8,
        'reference' => 'Delivered to customer',
    ])->assertRedirect();

    $stock->refresh();
    expect((float) $stock->dispatched_qty)->toBe(8.0);
    expect($stock->onHand())->toBe(12.0);
    expect($stock->movements()->where('type', 'dispatched')->count())->toBe(1);
});

test('you cannot dispatch more than the on-hand quantity', function () {
    $warehouse = Warehouse::factory()->create();
    $stock = WarehouseStock::create(['warehouse_id' => $warehouse->id, 'item_description' => 'Widget', 'received_qty' => 5]);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.inventory.dispatch', $stock->id), [
        'quantity' => 9,
    ])->assertSessionHasErrors('quantity');

    expect((float) $stock->fresh()->dispatched_qty)->toBe(0.0);
});

test('warehouse portal pages render for a warehouse user', function () {
    $warehouse = Warehouse::factory()->create();
    $order = orderWithItems($warehouse);
    $user = warehouseUser($warehouse);

    $this->actingAs($user)->get(route('warehouse.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('warehouse.orders.index'))->assertOk()->assertSee($order->order_no);
    $this->actingAs($user)->get(route('warehouse.orders.show', $order->id))->assertOk();
});

test('non-warehouse users are blocked from the warehouse portal', function () {
    $this->actingAs(adminUser())->get(route('warehouse.dashboard'))->assertForbidden();
});

test('a warehouse user is redirected out of the admin panel to their portal', function () {
    $warehouse = Warehouse::factory()->create();

    $this->actingAs(warehouseUser($warehouse))->get(route('dashboard'))
        ->assertRedirect(route('warehouse.dashboard'));
});

/*
|--------------------------------------------------------------------------
| Phase 11B — Expenses
|--------------------------------------------------------------------------
*/

test('admin can add an expense category and a sub-category', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('expense.category.store'), ['name' => 'Utilities'])->assertRedirect();
    $parent = ExpenseCategory::whereNull('parent_id')->where('name', 'Utilities')->firstOrFail();

    $this->actingAs($admin)->post(route('expense.category.store'), ['name' => 'Electricity', 'parent_id' => $parent->id])->assertRedirect();

    expect(ExpenseCategory::where('name', 'Electricity')->first()->parent_id)->toBe($parent->id);
});

test('a warehouse expense paid from an account debits the ledger', function () {
    $warehouse = Warehouse::factory()->create();
    $account = PaymentAccount::factory()->create(['balance' => 10000]);
    $category = ExpenseCategory::create(['name' => 'Electricity']);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.expenses.store'), [
        'expense_category_id' => $category->id,
        'amount' => 1500,
        'expense_date' => '2026-07-14',
        'payment_account_id' => $account->id,
    ])->assertRedirect();

    expect(WarehouseExpense::where('warehouse_id', $warehouse->id)->count())->toBe(1);
    expect((float) $account->fresh()->balance)->toBe(8500.0);

    $txn = Transaction::where('source', 'warehouse_expense')->first();
    expect($txn)->not->toBeNull();
    expect((float) $txn->debit)->toBe(1500.0);
    expect($txn->transactionable_type)->toBe(WarehouseExpense::class);
});

test('deleting a warehouse expense reverses its ledger entry', function () {
    $warehouse = Warehouse::factory()->create();
    $account = PaymentAccount::factory()->create(['balance' => 5000]);
    $user = warehouseUser($warehouse);

    $this->actingAs($user)->post(route('warehouse.expenses.store'), [
        'amount' => 1000, 'expense_date' => '2026-07-14', 'payment_account_id' => $account->id,
    ]);
    $expense = WarehouseExpense::firstOrFail();
    expect((float) $account->fresh()->balance)->toBe(4000.0);

    $this->actingAs($user)->delete(route('warehouse.expenses.delete', $expense->id))->assertRedirect();

    expect((float) $account->fresh()->balance)->toBe(5000.0);
    expect(Transaction::where('source', 'warehouse_expense')->count())->toBe(0);
    expect(WarehouseExpense::count())->toBe(0);
});

test('an expense with no account records no ledger entry', function () {
    $warehouse = Warehouse::factory()->create();

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.expenses.store'), [
        'amount' => 300, 'expense_date' => '2026-07-14',
    ])->assertRedirect();

    expect(WarehouseExpense::count())->toBe(1);
    expect(Transaction::where('source', 'warehouse_expense')->count())->toBe(0);
});

test('a warehouse user cannot delete another warehouse expense', function () {
    $mine = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $expense = WarehouseExpense::create(['warehouse_id' => $other->id, 'amount' => 100, 'expense_date' => '2026-07-14']);

    $this->actingAs(warehouseUser($mine))->delete(route('warehouse.expenses.delete', $expense->id))->assertNotFound();
    expect(WarehouseExpense::count())->toBe(1);
});

test('the expense pages render', function () {
    $this->actingAs(adminUser())->get(route('expense.categories'))->assertOk();
    $this->actingAs(warehouseUser(Warehouse::factory()->create()))->get(route('warehouse.expenses.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Phase 11C — Staff & Salary
|--------------------------------------------------------------------------
*/

test('a warehouse user can add staff scoped to their warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.staff.store'), [
        'name' => 'Karim', 'designation' => 'Loader', 'monthly_salary' => 15000,
    ])->assertRedirect();

    $staff = WarehouseStaff::firstOrFail();
    expect($staff->warehouse_id)->toBe($warehouse->id);
    expect($staff->name)->toBe('Karim');
});

test('a warehouse user cannot open another warehouse staff', function () {
    $other = WarehouseStaff::create(['warehouse_id' => Warehouse::factory()->create()->id, 'name' => 'Theirs']);

    $this->actingAs(warehouseUser(Warehouse::factory()->create()))
        ->get(route('warehouse.staff.show', $other->id))->assertNotFound();
});

test('a salary payment from an account debits the ledger', function () {
    $warehouse = Warehouse::factory()->create();
    $account = PaymentAccount::factory()->create(['balance' => 50000]);
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim', 'monthly_salary' => 15000]);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.staff.salary.store', $staff->id), [
        'salary_month' => '2026-07', 'amount' => 15000, 'payment_date' => '2026-07-14', 'payment_account_id' => $account->id,
    ])->assertRedirect();

    expect(StaffSalaryPayment::count())->toBe(1);
    expect((float) $account->fresh()->balance)->toBe(35000.0);

    $txn = Transaction::where('source', 'staff_salary')->first();
    expect($txn)->not->toBeNull();
    expect((float) $txn->debit)->toBe(15000.0);
});

test('deleting a salary payment reverses the ledger', function () {
    $warehouse = Warehouse::factory()->create();
    $account = PaymentAccount::factory()->create(['balance' => 20000]);
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim']);
    $user = warehouseUser($warehouse);

    $this->actingAs($user)->post(route('warehouse.staff.salary.store', $staff->id), [
        'salary_month' => '2026-07', 'amount' => 5000, 'payment_date' => '2026-07-14', 'payment_account_id' => $account->id,
    ]);
    $payment = StaffSalaryPayment::firstOrFail();
    expect((float) $account->fresh()->balance)->toBe(15000.0);

    $this->actingAs($user)->delete(route('warehouse.staff.salary.delete', [$staff->id, $payment->id]))->assertRedirect();

    expect((float) $account->fresh()->balance)->toBe(20000.0);
    expect(Transaction::where('source', 'staff_salary')->count())->toBe(0);
    expect(StaffSalaryPayment::count())->toBe(0);
});

test('a salary payment without an account records no ledger entry', function () {
    $warehouse = Warehouse::factory()->create();
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim']);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.staff.salary.store', $staff->id), [
        'salary_month' => '2026-07', 'amount' => 5000, 'payment_date' => '2026-07-14',
    ])->assertRedirect();

    expect(StaffSalaryPayment::count())->toBe(1);
    expect(Transaction::where('source', 'staff_salary')->count())->toBe(0);
});

test('a titled staff document can be uploaded', function () {
    $warehouse = Warehouse::factory()->create();
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim']);

    $this->actingAs(warehouseUser($warehouse))->post(route('warehouse.staff.document.store', $staff->id), [
        'title' => 'National ID',
        'file' => UploadedFile::fake()->create('nid.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $doc = $staff->documents()->firstOrFail();
    expect($doc->title)->toBe('National ID');

    // Clean up the file this test wrote to public/upload.
    @unlink(public_path('upload/staff_documents/'.$doc->file));
});

test('the staff pages render', function () {
    $warehouse = Warehouse::factory()->create();
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim']);
    $user = warehouseUser($warehouse);

    $this->actingAs($user)->get(route('warehouse.staff.index'))->assertOk()->assertSee('Karim');
    $this->actingAs($user)->get(route('warehouse.staff.show', $staff->id))->assertOk();
});
