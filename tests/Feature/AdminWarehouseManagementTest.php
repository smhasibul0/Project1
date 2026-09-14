<?php

use App\Models\Warehouse;
use App\Models\WarehouseExpense;
use App\Models\WarehouseStaff;
use App\Support\CurrentWarehouse;

test('an admin can enter manage mode for a warehouse and browse its portal', function () {
    $warehouse = Warehouse::factory()->create();
    $admin = adminUser();

    $this->actingAs($admin)->get(route('warehouse.manage', $warehouse->id))
        ->assertRedirect(route('warehouse.dashboard'));

    expect(session(CurrentWarehouse::SESSION_KEY))->toBe($warehouse->id);

    $this->actingAs($admin)->get(route('warehouse.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('warehouse.inventory.index'))->assertOk();
    $this->actingAs($admin)->get(route('warehouse.expenses.index'))->assertOk();
    $this->actingAs($admin)->get(route('warehouse.staff.index'))->assertOk();
});

test('an admin without a picked warehouse is bounced from the portal to the warehouse list', function () {
    $this->actingAs(adminUser())->get(route('warehouse.dashboard'))
        ->assertRedirect(route('warehouses.index'));
});

test('actions while managing are scoped to the managed warehouse', function () {
    $managed = Warehouse::factory()->create();
    Warehouse::factory()->create(); // another warehouse that must not be touched
    $admin = adminUser();

    $this->actingAs($admin)->get(route('warehouse.manage', $managed->id));

    $this->actingAs($admin)->post(route('warehouse.expenses.store'), [
        'amount' => 250, 'expense_date' => '2026-07-26',
    ])->assertRedirect();
    $this->actingAs($admin)->post(route('warehouse.staff.store'), [
        'name' => 'Rahim',
    ])->assertRedirect();

    expect(WarehouseExpense::sole()->warehouse_id)->toBe($managed->id);
    expect(WarehouseStaff::sole()->warehouse_id)->toBe($managed->id);
});

test('exiting manage mode clears the session and blocks the portal again', function () {
    $warehouse = Warehouse::factory()->create();
    $admin = adminUser();

    $this->actingAs($admin)->get(route('warehouse.manage', $warehouse->id));
    $this->actingAs($admin)->get(route('warehouse.manage.exit'))
        ->assertRedirect(route('warehouses.index'));

    expect(session(CurrentWarehouse::SESSION_KEY))->toBeNull();
    $this->actingAs($admin)->get(route('warehouse.dashboard'))
        ->assertRedirect(route('warehouses.index'));
});

test('manage mode is admin-only even with the warehouse permission', function () {
    $warehouse = Warehouse::factory()->create();
    $staff = userWithPermissions(['warehouses.view', 'warehouses.enter'], 'staff');

    $this->actingAs($staff)->get(route('warehouse.manage', $warehouse->id))->assertForbidden();
    $this->actingAs($staff)->get(route('warehouse.dashboard'))->assertForbidden();
});
