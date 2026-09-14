<?php

use App\Models\PaymentAccount;
use App\Models\Warehouse;
use App\Models\WarehouseStaff;

/**
 * Payment account balances are admin-only: other roles pick an account to pay
 * from without seeing what it holds.
 */
function balanceAccount(): PaymentAccount
{
    return PaymentAccount::factory()->create(['name' => 'Prime Bank', 'balance' => 987654.32]);
}

test('a warehouse user never sees account balances in payment selectors', function () {
    $account = balanceAccount();
    $warehouse = Warehouse::factory()->create();
    $user = warehouseUser($warehouse);
    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim', 'monthly_salary' => 5000]);

    foreach ([
        route('warehouse.expenses.index'),
        route('warehouse.payroll.index'),
        route('warehouse.staff.show', $staff->id),
    ] as $url) {
        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee($account->name)
            ->assertDontSee('987,654.32');
    }
});

test('an admin still sees account balances in payment selectors', function () {
    $account = balanceAccount();
    $warehouse = Warehouse::factory()->create();
    $admin = adminUser();

    $this->actingAs($admin)->get(route('warehouse.manage', $warehouse->id));

    $this->actingAs($admin)->get(route('warehouse.expenses.index'))
        ->assertOk()
        ->assertSee($account->name)
        ->assertSee('987,654.32');
});

test('a non-admin admin-panel user does not see the cash and bank dashboard tile', function () {
    balanceAccount();
    $staffUser = userWithPermissions(['dashboard.view'], 'ops');

    $this->actingAs($staffUser)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Cash &amp; bank balance', false);

    $this->actingAs(adminUser())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cash &amp; bank balance', false);
});
