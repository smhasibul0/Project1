<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->category = Category::factory()->create();
    $this->unit = Unit::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
});

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Product',
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'cost_price' => 100,
        'selling_price' => 150,
        'tax_rate' => 0,
        'alert_quantity' => 5,
        'hs_code' => '8471.30.00',
        'country_of_origin' => 'China',
        'is_active' => '1',
    ], $overrides);
}

test('a product can be created with an auto-generated code and opening stock', function () {
    $response = $this->actingAs($this->user)->post(route('product.store'), productPayload([
        'stock' => [$this->warehouse->id => 25],
    ]));

    $response->assertRedirect(route('products.index'));
    $response->assertSessionHas('success');

    $product = Product::firstOrFail();
    expect($product->code)->toBe('P0001');
    expect($product->name)->toBe('Test Product');
    expect($product->hs_code)->toBe('8471.30.00');
    expect($product->stocks()->where('warehouse_id', $this->warehouse->id)->value('quantity'))->toEqual('25.00');
    expect($product->totalStock())->toEqual(25.0);
});

test('product codes increment sequentially when left blank', function () {
    $this->actingAs($this->user)->post(route('product.store'), productPayload(['name' => 'A']));
    $this->actingAs($this->user)->post(route('product.store'), productPayload(['name' => 'B']));

    expect(Product::orderBy('id')->pluck('code')->all())->toBe(['P0001', 'P0002']);
});

test('a product can use an explicit unique code', function () {
    $this->actingAs($this->user)->post(route('product.store'), productPayload(['code' => 'SKU-100']));

    expect(Product::firstOrFail()->code)->toBe('SKU-100');
});

test('creating a product requires name, category and unit', function () {
    $response = $this->actingAs($this->user)->post(route('product.store'), ['name' => '']);

    $response->assertSessionHasErrors(['name', 'category_id', 'unit_id']);
    expect(Product::count())->toBe(0);
});

test('a product update re-syncs its warehouse stock', function () {
    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]);
    $product->stocks()->create(['warehouse_id' => $this->warehouse->id, 'quantity' => 10]);

    $this->actingAs($this->user)->put(route('product.update', $product->id), productPayload([
        'name' => 'Updated',
        'stock' => [$this->warehouse->id => 40],
    ]));

    $product->refresh();
    expect($product->name)->toBe('Updated');
    expect($product->stocks()->where('warehouse_id', $this->warehouse->id)->value('quantity'))->toEqual('40.00');
});

test('a product can be deleted', function () {
    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]);

    $this->actingAs($this->user)->delete(route('product.delete', $product->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Product::count())->toBe(0);
});

test('the products list and create pages load', function () {
    Product::factory()->create([
        'name' => 'Listed Product',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]);

    $this->actingAs($this->user)->get(route('products.index'))->assertOk()->assertSee('Listed Product');
    $this->actingAs($this->user)->get(route('products.create'))->assertOk()->assertSee('Add Product');
});
