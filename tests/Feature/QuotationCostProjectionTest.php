<?php

use App\Models\Contact;
use App\Models\CostCategory;
use App\Models\Order;
use App\Models\Quotation;
use App\Support\DutyCalculator;
use App\Support\PackingListParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->customer = Contact::factory()->customer()->create();
});

test('the duty calculator follows the BD customs cascade', function () {
    // AV 10000: CD 25% = 2500, RD 3% = 300, SD 10% on 12800 = 1280,
    // VAT 15% on 14080 = 2112, AIT 5% on AV = 500, AT 4% on 14080 = 563.20
    $duty = DutyCalculator::calculate(10000, [
        'cd_rate' => 25, 'rd_rate' => 3, 'sd_rate' => 10,
        'vat_rate' => 15, 'ait_rate' => 5, 'at_rate' => 4,
    ]);

    expect($duty['cd'])->toEqual(2500.0);
    expect($duty['rd'])->toEqual(300.0);
    expect($duty['sd'])->toEqual(1280.0);
    expect($duty['vat'])->toEqual(2112.0);
    expect($duty['ait'])->toEqual(500.0);
    expect($duty['at'])->toEqual(563.20);
    expect($duty['total'])->toEqual(7255.20);
});

test('a quotation can be created without a packing list', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'sell_rate_per_cbm' => 100,
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
    ]);

    $response->assertRedirect(route('quotations.index'))->assertSessionHasNoErrors();

    $quotation = Quotation::firstOrFail();
    expect($quotation->packing_list_path)->toBeNull();
    expect($quotation->customer_charge)->toEqual('100.00');
});

test('an uploaded packing list is still stored when one is given', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
    ]);

    $quotation = Quotation::firstOrFail();
    expect($quotation->packing_list_path)->not->toBeNull();

    @unlink(public_path('upload/quotation/'.$quotation->packing_list_path));
});

test('the packing list parser groups identical cartons and extracts metadata', function () {
    $upload = fakePackingList([
        ['Ex7 Hand pump', 30, 20.8, 20.0, 0.127],
        ['Ex7 Hand pump', 30, 20.8, 20.0, 0.127],
        ['GP20 Hand pump', 30, 22.7, 22.0, 0.127],
    ]);

    $result = (new PackingListParser)->parse($upload->getRealPath());

    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]['description'])->toBe('Ex7 Hand pump');
    expect($result['items'][0]['cartons'])->toBe(2);
    expect($result['items'][0]['quantity'])->toEqual(60.0);
    expect($result['items'][0]['gross_weight'])->toEqual(41.6);
    expect($result['items'][0]['cbm'])->toEqual(0.254);
    expect($result['customer'])->toContain('Test Customer');
    expect($result['supplier'])->toContain('Test Supplier');
    expect($result['totals']['quantity'])->toEqual($result['sheet_totals']['quantity']);
});

test('the parse endpoint returns quotation-ready rows', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.parse.packing'), [
        'packing_list' => fakePackingList(),
    ]);

    $response->assertOk()
        ->assertJsonPath('items.0.description', 'Ex7 Hand pump')
        ->assertJsonPath('totals.cartons', 3);
});

test('storing a quotation computes duties, expenses and the projected profit', function () {
    $category = CostCategory::factory()->create();

    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 3000,
        'items' => [[
            'description' => 'Ex7 Hand pump',
            'package_quantity' => 100,
            'cbm' => 10,
            'declared_value' => 10000,
            // Assessable value defaults to the declared value + 1% landing = 10100
            'cd_rate' => 25, 'rd_rate' => 0, 'sd_rate' => 0,
            'vat_rate' => 15, 'ait_rate' => 5, 'at_rate' => 0,
        ]],
        'expenses' => [
            ['cost_category_id' => $category->id, 'title' => 'LC opening commission', 'amount' => 1200],
            ['title' => 'Local transport', 'amount' => 800],
        ],
    ]);

    $response->assertRedirect(route('quotations.index'));

    $q = Quotation::with(['items', 'expenses'])->firstOrFail();
    $item = $q->items->first();

    // AV 10100 -> CD 2525, VAT 15% on 12625 = 1893.75, AIT 505 => TTI 4923.75
    expect($item->assessable_value)->toEqual('10100.00');
    expect($item->duty_amount)->toEqual('4923.75');
    expect($q->total_duty)->toEqual('4923.75');

    expect($q->expenses)->toHaveCount(2);
    expect($q->expenses->sum('amount'))->toEqual(2000.0);

    // Cost: duty 4923.75 + expenses 2000 = 6923.75 (nothing is bought); charge 10 CBM x 3000
    expect($q->customer_charge)->toEqual('30000.00');
    expect($q->projected_cost_total)->toEqual('6923.75');
    expect($q->projected_profit)->toEqual('23076.25');

    // The uploaded packing list is stored against the quotation.
    expect($q->packing_list_path)->not->toBeNull();
    $stored = public_path('upload/quotation/'.$q->packing_list_path);
    expect(file_exists($stored))->toBeTrue();
    @unlink($stored);
});

test('a manually overridden assessable value is respected', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'items' => [[
            'package_quantity' => 10,
            'cbm' => 2,
            'declared_value' => 1000,
            'assessable_value' => 5000,
            'vat_rate' => 15,
        ]],
    ]);

    $item = Quotation::firstOrFail()->items()->firstOrFail();
    expect($item->assessable_value)->toEqual('5000.00');
    expect($item->duty_amount)->toEqual('750.00');

    $q = Quotation::firstOrFail();
    $stored = public_path('upload/quotation/'.$q->packing_list_path);
    @unlink($stored);
});

test('updating a quotation replaces its predicted expenses', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
        'expenses' => [['title' => 'Old expense', 'amount' => 100]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->put(route('quotation.update', $q->id), [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'declared_value' => 5]],
        'expenses' => [['title' => 'New expense', 'amount' => 250]],
    ]);

    $q->refresh()->load('expenses');
    expect($q->expenses)->toHaveCount(1);
    expect($q->expenses->first()->title)->toBe('New expense');
    expect((float) $q->expenses->first()->amount)->toEqual(250.0);

    @unlink(public_path('upload/quotation/'.$q->packing_list_path));
});

test('converting a quotation seeds the order costs with projections', function () {
    $category = CostCategory::factory()->create();

    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 3000,
        'items' => [[
            'description' => 'Ex7 Hand pump',
            'hs_code' => '8413.20.00',
            'package_quantity' => 100,
            'cbm' => 10,
            'declared_value' => 10000,
            'cd_rate' => 25, 'vat_rate' => 15, 'ait_rate' => 5,
        ]],
        'expenses' => [
            ['cost_category_id' => $category->id, 'title' => 'LC opening commission', 'amount' => 1200],
        ],
    ]);
    $quotation = Quotation::firstOrFail();
    $quotation->update(['status' => 'accepted']);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))->assertRedirect();

    $order = Order::with(['items', 'costs'])->firstOrFail();
    expect($order->items->first()->item_description)->toBe('Ex7 Hand pump');
    expect($order->items->first()->hs_code)->toBe('8413.20.00');

    // Duty rides on the items, so only the predicted expense becomes a cost line.
    expect($order->costs)->toHaveCount(1);
    expect($order->duty_total)->toEqual($quotation->total_duty);
    expect($order->sell_rate_per_cbm)->toEqual('3000.00');

    $lcCost = $order->costs->firstWhere('title', 'LC opening commission');
    expect($lcCost)->not->toBeNull();
    expect((float) $lcCost->amount)->toEqual(1200.0);
    expect($lcCost->cost_category_id)->toBe($category->id);

    @unlink(public_path('upload/quotation/'.$quotation->packing_list_path));
});

test('LCL predicted freight is computed from rate x total item CBM', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'freight_type' => 'lcl',
        'freight_rate' => 55,
        'sell_rate_per_cbm' => 300,
        'items' => [
            ['package_quantity' => 100, 'declared_value' => 1000, 'cbm' => 6],
            ['package_quantity' => 50, 'declared_value' => 500, 'cbm' => 4],
        ],
    ]);

    $q = Quotation::firstOrFail();
    expect($q->freight_type)->toBe('lcl');
    expect($q->total_cbm)->toEqual('10.0000');
    expect($q->freight_amount)->toEqual('550.00'); // 55 x 10 CBM

    // Cost is the freight alone (no duty rates given); charge is 10 CBM at 300.
    expect($q->projected_cost_total)->toEqual('550.00');
    expect($q->customer_charge)->toEqual('3000.00');
    expect($q->projected_profit)->toEqual('2450.00');

    @unlink(public_path('upload/quotation/'.$q->packing_list_path));
});

test('FCL predicted freight stores the flat container price and size', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'freight_type' => 'fcl',
        'freight_container_size' => '40HQ',
        'freight_amount' => 3200,
        'sell_rate_per_cbm' => 300,
        'items' => [['package_quantity' => 10, 'declared_value' => 1000, 'cbm' => 20]],
    ]);

    $q = Quotation::firstOrFail();
    expect($q->freight_type)->toBe('fcl');
    expect($q->freight_container_size)->toBe('40HQ');
    expect($q->freight_amount)->toEqual('3200.00');       // flat, not per CBM
    expect($q->projected_cost_total)->toEqual('3200.00');
    expect($q->customer_charge)->toEqual('6000.00');      // 20 CBM at 300
    expect($q->projected_profit)->toEqual('2800.00');

    @unlink(public_path('upload/quotation/'.$q->packing_list_path));
});

test('converting an LCL quotation carries both rates onto the order', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'freight_type' => 'lcl',
        'freight_rate' => 50,
        'sell_rate_per_cbm' => 200,
        'items' => [['package_quantity' => 10, 'declared_value' => 1000, 'cbm' => 8]],
    ]);
    $quotation = Quotation::firstOrFail();
    $quotation->update(['status' => 'accepted']);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->cost_rate_per_cbm)->toEqual('50.00');
    expect($order->sell_rate_per_cbm)->toEqual('200.00');
    expect($order->total_cbm)->toEqual('8.0000');
    expect($order->freight_cost)->toEqual('400.00');   // 50 x 8 CBM
    expect($order->subtotal)->toEqual('1600.00');      // 200 x 8 CBM
    expect($order->profit)->toEqual('1200.00');
    expect($order->costs)->toHaveCount(0);             // an LCL rate is not a cost line

    @unlink(public_path('upload/quotation/'.$quotation->packing_list_path));
});

test('converting an FCL quotation books the flat container price as a cost', function () {
    $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'freight_type' => 'fcl',
        'freight_container_size' => '40HQ',
        'freight_amount' => 3200,
        'sell_rate_per_cbm' => 300,
        'items' => [['package_quantity' => 10, 'declared_value' => 1000, 'cbm' => 20]],
    ]);
    $quotation = Quotation::firstOrFail();
    $quotation->update(['status' => 'accepted']);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))->assertRedirect();

    $freightCost = Order::firstOrFail()->costs()->where('title', 'like', 'FCL freight%')->first();
    expect($freightCost)->not->toBeNull();
    expect((float) $freightCost->amount)->toEqual(3200.0);
    expect($freightCost->note)->toContain($quotation->quotation_no);

    @unlink(public_path('upload/quotation/'.$quotation->packing_list_path));
});
