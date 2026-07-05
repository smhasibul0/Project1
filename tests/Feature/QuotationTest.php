<?php

use App\Models\Category;
use App\Models\Contact;
use App\Models\PackingType;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TransportationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create();
    $this->supplier = Contact::factory()->supplier()->create();
    $this->category = Category::factory()->create();
    $this->mode = TransportationMode::create(['name' => 'Sea']);
    $this->packing = PackingType::create(['name' => 'Carton']);
});

test('a quotation is created with items and computed totals & profit', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'query_received_date' => '2026-07-05',
        'customer_id' => $this->customer->id,
        'status' => 'submitted',
        'submitted_to_customer' => '1',
        'items' => [
            [
                'category_id' => $this->category->id,
                'hs_code' => '9503009',
                'transportation_mode_id' => $this->mode->id,
                'country_of_loading' => 'China',
                'packing_type_id' => $this->packing->id,
                'package_quantity' => 10,
                'supplier_asking_price' => 100,
                'supplier_id' => $this->supplier->id,
                'our_asking_price' => 150,
            ],
            [
                'package_quantity' => 5,
                'supplier_asking_price' => 200,
                'our_asking_price' => 300,
            ],
        ],
    ]);

    $response->assertRedirect(route('quotations.index'));
    $response->assertSessionHas('success');

    $q = Quotation::with('items')->firstOrFail();
    expect($q->quotation_no)->toBe('Q0001');
    expect($q->customer_id)->toBe($this->customer->id);
    expect($q->submitted_to_customer)->toBeTrue();
    expect($q->items)->toHaveCount(2);

    // Header aggregates: (150*10 + 300*5) = 3000 ; profit (50*10 + 100*5) = 1000 ; margin 33.33
    expect($q->grand_total)->toEqual('3000.00');
    expect($q->total_profit)->toEqual('1000.00');
    expect($q->profit_margin)->toEqual('33.33');

    $item = $q->items->first();
    expect($item->line_total)->toEqual('1500.00');
    expect($item->unit_profit)->toEqual('50.00');
    expect($item->total_profit)->toEqual('500.00');
    expect($item->profit_margin)->toEqual('33.33');
    expect($item->hs_code)->toBe('9503009');
});

test('quotation numbers increment sequentially', function () {
    $payload = fn () => [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
    ];

    $this->actingAs($this->user)->post(route('quotation.store'), $payload());
    $this->actingAs($this->user)->post(route('quotation.store'), $payload());

    expect(Quotation::orderBy('id')->pluck('quotation_no')->all())->toBe(['Q0001', 'Q0002']);
});

test('a quotation requires at least one item', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
    ]);

    $response->assertSessionHasErrors('items');
    expect(Quotation::count())->toBe(0);
});

test('updating a quotation replaces items and recomputes totals', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 2, 'our_asking_price' => 100, 'supplier_asking_price' => 60]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->put(route('quotation.update', $q->id), [
        'customer_id' => $this->customer->id,
        'status' => 'accepted',
        'items' => [['package_quantity' => 3, 'our_asking_price' => 200, 'supplier_asking_price' => 150]],
    ]);

    $q->refresh()->load('items');
    expect($q->items)->toHaveCount(1);
    expect($q->status)->toBe('accepted');
    expect($q->grand_total)->toEqual('600.00');   // 200*3
    expect($q->total_profit)->toEqual('150.00');  // (200-150)*3
});

test('a quotation can be deleted with its items', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->delete(route('quotation.delete', $q->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Quotation::count())->toBe(0);
    expect(QuotationItem::count())->toBe(0);
});

test('admin can add transportation modes and packing types', function () {
    $this->actingAs($this->user)->post(route('transportation.mode.store'), ['name' => 'Rail']);
    $this->actingAs($this->user)->post(route('packing.type.store'), ['name' => 'Pallet']);

    expect(TransportationMode::where('name', 'Rail')->exists())->toBeTrue();
    expect(PackingType::where('name', 'Pallet')->exists())->toBeTrue();
});

test('the quotation list and create pages load', function () {
    $this->actingAs($this->user)->get(route('quotations.index'))->assertOk();
    $this->actingAs($this->user)->get(route('quotations.create'))->assertOk()->assertSee('Add Quotation');
});
