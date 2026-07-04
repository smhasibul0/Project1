<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('a category can be created, updated and deleted', function () {
    $this->actingAs($this->user)->post(route('category.store'), [
        'name' => 'Electronics',
        'code' => 'ELEC',
    ])->assertRedirect()->assertSessionHas('success');

    $category = Category::firstOrFail();
    expect($category->name)->toBe('Electronics');

    $this->actingAs($this->user)->put(route('category.update', $category->id), ['name' => 'Gadgets']);
    expect($category->fresh()->name)->toBe('Gadgets');

    $this->actingAs($this->user)->delete(route('category.delete', $category->id));
    expect(Category::count())->toBe(0);
});

test('a brand can be created and deleted', function () {
    $this->actingAs($this->user)->post(route('brand.store'), ['name' => 'Yamaha']);
    $brand = Brand::firstOrFail();
    expect($brand->name)->toBe('Yamaha');

    $this->actingAs($this->user)->delete(route('brand.delete', $brand->id));
    expect(Brand::count())->toBe(0);
});

test('a unit can be created with a base unit conversion', function () {
    $base = Unit::factory()->create(['name' => 'Piece', 'short_name' => 'pc']);

    $this->actingAs($this->user)->post(route('unit.store'), [
        'name' => 'Dozen',
        'short_name' => 'dz',
        'base_unit_id' => $base->id,
        'conversion_factor' => 12,
    ]);

    $unit = Unit::where('name', 'Dozen')->firstOrFail();
    expect($unit->base_unit_id)->toBe($base->id);
    expect($unit->conversion_factor)->toEqual('12.0000');
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

test('the inventory index pages load', function () {
    Category::factory()->create(['name' => 'Cat Load']);
    Brand::factory()->create(['name' => 'Brand Load']);
    Unit::factory()->create(['name' => 'Unit Load']);
    Warehouse::factory()->create(['name' => 'WH Load']);

    $this->actingAs($this->user)->get(route('categories.index'))->assertOk()->assertSee('Cat Load');
    $this->actingAs($this->user)->get(route('brands.index'))->assertOk()->assertSee('Brand Load');
    $this->actingAs($this->user)->get(route('units.index'))->assertOk()->assertSee('Unit Load');
    $this->actingAs($this->user)->get(route('warehouses.index'))->assertOk()->assertSee('WH Load');
});
