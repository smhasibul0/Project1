<?php

use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('a warehouse can be created and toggles active from the checkbox', function () {
    $this->actingAs($this->user)->post(route('warehouse.store'), [
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
        'is_active' => '1',
    ]);
    $warehouse = Warehouse::firstOrFail();
    expect($warehouse->is_active)->toBeTrue();

    // No checkbox in payload => inactive
    $this->actingAs($this->user)->put(route('warehouse.update', $warehouse->id), ['name' => 'Main Warehouse']);
    expect($warehouse->fresh()->is_active)->toBeFalse();
});

test('the warehouse list loads', function () {
    Warehouse::factory()->create(['name' => 'WH Load']);

    $this->actingAs($this->user)->get(route('warehouses.index'))->assertOk()->assertSee('WH Load');
});
