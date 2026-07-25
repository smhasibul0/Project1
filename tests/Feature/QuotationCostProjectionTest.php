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

test('a packing list is mandatory when creating a quotation', function () {
    $response = $this->actingAs($this->user)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
    ]);

    $response->assertSessionHasErrors('packing_list');
    expect(Quotation::count())->toBe(0);
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
        'items' => [[
            'description' => 'Ex7 Hand pump',
            'package_quantity' => 100,
            'supplier_asking_price' => 100,
            'our_asking_price' => 300,
            // Assessable value defaults to 100*100*1.01 = 10100
            'cd_rate' => 25, 'rd_rate' => 0, 'sd_rate' => 0,
            'vat_rate' => 15, 'ait_rate' => 5, 'at_rate' => 0,
        ]],
        'expenses' => [
            ['expense_group' => 'lc', 'cost_category_id' => $category->id, 'title' => 'LC opening commission', 'amount' => 1200],
            ['expense_group' => 'custom', 'title' => 'Local transport', 'amount' => 800],
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
    expect($q->lcExpenses()->sum('amount'))->toEqual(1200.0);
    expect($q->customExpenses()->sum('amount'))->toEqual(800.0);

    // Projection: goods 10000 + duty 4923.75 + expenses 2000 = 16923.75; asking 30000
    expect($q->projected_cost_total)->toEqual('16923.75');
    expect($q->projected_profit)->toEqual('13076.25');

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
            'supplier_asking_price' => 100,
            'our_asking_price' => 200,
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
        'items' => [['package_quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
        'expenses' => [['expense_group' => 'custom', 'title' => 'Old expense', 'amount' => 100]],
    ]);
    $q = Quotation::firstOrFail();

    $this->actingAs($this->user)->put(route('quotation.update', $q->id), [
        'customer_id' => $this->customer->id,
        'items' => [['package_quantity' => 1, 'our_asking_price' => 10, 'supplier_asking_price' => 5]],
        'expenses' => [['expense_group' => 'custom', 'title' => 'New expense', 'amount' => 250]],
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
        'items' => [[
            'description' => 'Ex7 Hand pump',
            'package_quantity' => 100,
            'supplier_asking_price' => 100,
            'our_asking_price' => 300,
            'cd_rate' => 25, 'vat_rate' => 15, 'ait_rate' => 5,
        ]],
        'expenses' => [
            ['expense_group' => 'lc', 'cost_category_id' => $category->id, 'title' => 'LC opening commission', 'amount' => 1200],
        ],
    ]);
    $quotation = Quotation::firstOrFail();
    $quotation->update(['status' => 'accepted']);

    $this->actingAs($this->user)->post(route('order.from.quotation', $quotation->id))->assertRedirect();

    $order = Order::with(['items', 'costs'])->firstOrFail();
    expect($order->items->first()->item_description)->toBe('Ex7 Hand pump');
    expect($order->costs)->toHaveCount(2);

    $dutyCost = $order->costs->firstWhere('title', 'Duty & taxes (projected)');
    expect($dutyCost)->not->toBeNull();
    expect((float) $dutyCost->amount)->toEqual((float) $quotation->total_duty);
    expect($dutyCost->note)->toContain($quotation->quotation_no);

    $lcCost = $order->costs->firstWhere('title', 'LC opening commission');
    expect($lcCost)->not->toBeNull();
    expect((float) $lcCost->amount)->toEqual(1200.0);
    expect($lcCost->cost_category_id)->toBe($category->id);

    @unlink(public_path('upload/quotation/'.$quotation->packing_list_path));
});
