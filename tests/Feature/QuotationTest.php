<?php

use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\HsCode;
use App\Models\PackingType;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TransportationMode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create();
    $this->hsCode = HsCode::factory()->create(['code' => '9503.00.90', 'cd_rate' => 0, 'vat_rate' => 0, 'ait_rate' => 0, 'at_rate' => 0]);
    $this->mode = TransportationMode::create(['name' => 'Sea']);
    $this->packing = PackingType::create(['name' => 'Carton']);
});

test('a quotation is created with items and computed totals & profit', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'query_received_date' => '2026-07-05',
        'customer_id' => $this->customer->id,
        'action' => 'send',
        'packing_list' => fakePackingList(),
        'transportation_mode_id' => $this->mode->id,
        'country_of_loading' => 'China',
        'freight_type' => 'lcl',
        'freight_rate' => 50,
        'sell_rate_per_cbm' => 100,
        'items' => [
            [
                'hs_code_id' => $this->hsCode->id,
                'hs_code' => '9503.00.90',
                'description' => 'Toys',
                'packing_type_id' => $this->packing->id,
                'package_quantity' => 10,
                'cbm' => 6,
                'declared_value' => 1000,
            ],
            [
                'package_quantity' => 5,
                'cbm' => 4,
                'declared_value' => 500,
            ],
        ],
    ]);

    $response->assertRedirect(route('quotations.index'));
    $response->assertSessionHas('success');

    $q = Quotation::with('items')->firstOrFail();
    expect($q->quotation_no)->toBe('Q0001');
    expect($q->customer_id)->toBe($this->customer->id);
    expect($q->status)->toBe('quoted');            // "Send to Customer" quotes it
    expect($q->submitted_to_customer)->toBeTrue();
    expect($q->items)->toHaveCount(2);

    // One shipment, so the transport details sit on the quotation itself.
    expect($q->transportation_mode_id)->toBe($this->mode->id);
    expect($q->country_of_loading)->toBe('China');

    // 10 CBM at 100 = 1000 charged; freight 10 x 50 = 500 cost; no duty on these rates.
    expect($q->total_cbm)->toEqual('10.0000');
    expect($q->customer_charge)->toEqual('1000.00');
    expect($q->grand_total)->toEqual('1000.00');
    expect($q->freight_amount)->toEqual('500.00');
    expect($q->projected_cost_total)->toEqual('500.00');
    expect($q->projected_profit)->toEqual('500.00');
    expect($q->profit_margin)->toEqual('50.00');

    $item = $q->items->first();
    expect($item->line_total)->toEqual('600.00');    // 6 CBM at 100
    expect($item->total_profit)->toEqual('300.00');  // less its 300 share of the freight
    expect($item->hs_code)->toBe('9503.00.90');
    expect($item->hs_code_id)->toBe($this->hsCode->id);
});

test('quotation numbers increment sequentially', function () {
    $payload = fn () => [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
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
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 100,
        'items' => [['package_quantity' => 2, 'cbm' => 2, 'declared_value' => 60]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->put(route('quotation.update', $q->id), [
        'customer_id' => $this->customer->id,
        'action' => 'send',
        'sell_rate_per_cbm' => 200,
        'items' => [['package_quantity' => 3, 'cbm' => 3, 'declared_value' => 150]],
    ]);

    $q->refresh()->load('items');
    expect($q->items)->toHaveCount(1);
    expect($q->status)->toBe('quoted');           // sending to the customer
    expect($q->grand_total)->toEqual('600.00');   // 3 CBM at 200
    expect($q->total_profit)->toEqual('600.00');  // nothing bought, no freight rate given
});

test('a quotation can be deleted with its items', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->delete(route('quotation.delete', $q->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Quotation::count())->toBe(0);
    expect(QuotationItem::count())->toBe(0);
});

test('admin can deny a quotation (e.g. after a negotiation request)', function () {
    $q = Quotation::factory()->create(['status' => 'negotiating']);

    $this->actingAs($this->user)->post(route('quotation.deny', $q->id))
        ->assertRedirect()->assertSessionHas('success');

    expect($q->fresh()->status)->toBe('rejected');
});

test('the quotation requests page shows only pending requests', function () {
    Quotation::factory()->create(['status' => 'requested']);
    Quotation::factory()->create(['status' => 'quoted']);

    $response = $this->actingAs($this->user)->get(route('quotation.requests'));
    $response->assertOk();
    expect($response->viewData('quotations'))->toHaveCount(1);
});

test('admin can add transportation modes and packing types', function () {
    $this->actingAs($this->user)->post(route('transportation.mode.store'), ['name' => 'Rail']);
    $this->actingAs($this->user)->post(route('packing.type.store'), ['name' => 'Pallet']);

    expect(TransportationMode::where('name', 'Rail')->exists())->toBeTrue();
    expect(PackingType::where('name', 'Pallet')->exists())->toBeTrue();
});

test('the printable quotation shows the customer price but never our costs', function () {
    CompanySetting::create(['company_name' => 'Redwan Trading Corporation', 'currency' => 'BDT']);

    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'freight_type' => 'lcl',
        'freight_rate' => 50,          // our cost per CBM
        'sell_rate_per_cbm' => 100,    // what the customer pays per CBM
        'items' => [[
            'hs_code' => '9503.00.90',
            'description' => 'Plastic toys',
            'package_quantity' => 10,
            'cbm' => 8,
            'declared_value' => 20000,
            'cd_rate' => 25, 'vat_rate' => 15,
        ]],
        'expenses' => [['title' => 'Port handling', 'amount' => 1500]],
    ]);

    $quotation = Quotation::firstOrFail();
    $response = $this->actingAs($this->user)->get(route('quotation.print', $quotation->id));

    $response->assertOk()
        ->assertSee('Redwan Trading Corporation')
        ->assertSee($quotation->quotation_no)
        ->assertSee('9503.00.90')
        ->assertSee('Plastic toys')
        ->assertSee('800.00')            // 8 CBM at 100 — what they pay
        ->assertSee('Eight Hundred Taka Only');

    // Nothing from our cost side may appear.
    $response->assertDontSee('Port handling')
        ->assertDontSee('Duty')
        ->assertDontSee('Profit')
        ->assertDontSee('400.00')        // freight cost, 8 CBM at 50
        ->assertDontSee('1,500.00');     // the additional cost

    expect((float) $quotation->projected_cost_total)->toBeGreaterThan(0.0);
});

test('the quotation list and create pages load', function () {
    $this->actingAs($this->user)->get(route('quotations.index'))->assertOk();
    $this->actingAs($this->user)->get(route('quotations.create'))->assertOk()->assertSee('Add Quotation');
});
