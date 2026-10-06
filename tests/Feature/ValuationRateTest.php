<?php

use App\Models\Contact;
use App\Models\ExchangeRate;
use App\Models\HsCode;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Models\ValuationRate;
use App\Support\ValuationReportParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = adminUser();
});

test('a valuation report is read bill by bill, and its highest assessed price per kg kept', function () {
    [$rate] = (new ValuationReportParser)->parse(fakeValuationReport()->getRealPath());

    expect($rate['code_digits'])->toBe('79011210')
        ->and($rate['unit_price'])->toBe(5.71)
        ->and($rate['bills'])->toHaveCount(3)
        ->and($rate['period_from'])->toBe('2026-09-21')
        ->and($rate['period_to'])->toBe('2026-09-27');

    // "1,0074,951.31" splits into its own columns; the long BE number comes off the date.
    expect($rate['bills'][1])->toMatchArray(['be_no' => '1251322', 'be_date' => '2026-09-21', 'declared_unit_price' => 1.0, 'assessed_unit_price' => 3.84, 'net_weight' => 19543.0]);

    // A bill printed without a date takes the one above; a wrapped description is joined.
    expect($rate['bills'][2])->toMatchArray(['be_no' => '55190', 'be_date' => '2026-09-21', 'description' => 'ELECTROPLATING CHEMICAL ZINC INGOT', 'country' => 'IN', 'exporter' => 'NORTH EXPORTS']);
    expect($rate['bills'][0]['exporter'])->toBe('ACME TRADING DHAKA ROAD');
});

test('a report keeps the highest, lowest and most common assessed price per kg', function () {
    // Bills at 1.20, 3.84, 3.84 and 5.71 USD/kg.
    [$rate] = (new ValuationReportParser)->parse(fakeValuationReport([
        'HSCODE', '79011210', 'ValuationReport_Analysis',
        '601 4 66225 27/09/202679011210ZINC INGOT 1 USD 50.001.00 60.001.20 50.00 0IN ACME TRADING',
        '301 4 125132221/09/202679011210ZINC INGOTS 1 USD 19,543.001.0074,951.313.8419,543.00 0AU SAMPLE METALS',
        '301 4 125133321/09/202679011210ZINC INGOTS 1 USD 1,000.001.003,840.003.841,000.00 0AU SAMPLE METALS',
        '55190 79011210ZINC INGOT 2 USD 3,426.005.713,426.005.71 600.00 0IN NORTH EXPORTS',
    ])->getRealPath());

    expect($rate)->toMatchArray([
        'unit_price' => 5.71,
        'lowest_unit_price' => 1.2,
        'common_unit_price' => 3.84,
        'common_bills' => 2,
    ]);
});

test('when no price is more common than another, the higher one counts as most common', function () {
    $figures = ValuationReportParser::summarise([
        ['assessed_unit_price' => 1.2], ['assessed_unit_price' => 5.71],
        ['assessed_unit_price' => 1.2], ['assessed_unit_price' => 5.71],
        ['assessed_unit_price' => 3.0],
    ]);

    expect($figures)->toBe(['unit_price' => 5.71, 'lowest_unit_price' => 1.2, 'common_unit_price' => 5.71, 'common_bills' => 2]);
});

test('uploading a report stores the rate under the date given, with the PDF kept privately', function () {
    $this->actingAs($this->admin)->post(route('rates.store'), [
        'rate_date' => '2026-10-02',
        'reports' => [fakeValuationReport()],
    ])->assertRedirect(route('rates.index'))->assertSessionHas('success');

    $rate = ValuationRate::sole();
    expect($rate->hs_code)->toBe('7901.12.10')
        ->and($rate->rate_date->toDateString())->toBe('2026-10-02')
        ->and((float) $rate->unit_price)->toBe(5.71)
        ->and((float) $rate->lowest_unit_price)->toBe(1.2)
        ->and((float) $rate->common_unit_price)->toBe(5.71) // every bill differs; the higher counts
        ->and($rate->common_bills)->toBe(1)
        ->and($rate->bills_count)->toBe(3)
        ->and($rate->added_by)->toBe($this->admin->id);

    Storage::disk('local')->assertExists($rate->file_path);

    $this->actingAs($this->admin)->get(route('rates.pdf', $rate->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a PDF that is not a valuation report is refused and nothing is stored', function () {
    $this->actingAs($this->admin)->post(route('rates.store'), [
        'rate_date' => '2026-10-02',
        'reports' => [fakeValuationReport(['Invoice No 42', 'Thank you for your business'], 'invoice.pdf')],
    ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'invoice.pdf'));

    expect(ValuationRate::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('the rates page lists uploads and marks the newest per code as in use', function () {
    $older = ValuationRate::factory()->create(['rate_date' => '2026-08-01', 'unit_price' => 1.2]);
    $newer = ValuationRate::factory()->create(['rate_date' => '2026-10-01', 'unit_price' => 5.71]);

    expect($newer->isCurrent())->toBeTrue()->and($older->isCurrent())->toBeFalse();

    $this->actingAs($this->admin)->get(route('rates.index'))
        ->assertOk()
        ->assertSee('7901.12.10')
        ->assertSee('5.71 USD/kg')
        ->assertSee('In use');

    $this->actingAs($this->admin)->get(route('rates.show', $newer->id))
        ->assertOk()
        ->assertSee('Bills of Entry');
});

test('the dollar rate is saved with the day it was set, and the rates page converts at it', function () {
    $this->actingAs($this->admin)->post(route('exchange.rate.store'), ['rate_date' => ExchangeRate::today(), 'usd_rate' => 122.5])
        ->assertSessionHas('success');

    $rate = ExchangeRate::sole();
    expect((float) $rate->usd_rate)->toBe(122.5)
        ->and($rate->rate_date->toDateString())->toBe(ExchangeRate::today());

    ValuationRate::factory()->create(['unit_price' => 2]);

    $this->actingAs($this->admin)->get(route('rates.index'))
        ->assertOk()
        ->assertSee('1 USD = ৳ 122.50')
        ->assertSee('৳ 245.00/kg');
});

test('the HS lookup gives quotation staff the newest rate, and the customer portal none', function () {
    $hsCode = HsCode::factory()->create(['code' => '7901.12.10']);
    ValuationRate::factory()->create(['code_digits' => '79011210', 'rate_date' => '2026-08-01', 'unit_price' => 1.2]);
    ValuationRate::factory()->create([
        'code_digits' => '79011210', 'rate_date' => '2026-10-01', 'bills_count' => 30,
        'unit_price' => 5.71, 'common_unit_price' => 3.84, 'common_bills' => 12, 'lowest_unit_price' => 1.2,
    ]);

    $this->actingAs($this->admin)->getJson(route('hs.code.search', ['q' => '7901']))
        ->assertOk()
        ->assertJsonPath('0.id', $hsCode->id)
        ->assertJsonPath('0.reference.unit_price', 5.71)
        ->assertJsonPath('0.reference.rate_date', '2026-10-01')
        ->assertJsonPath('0.reference.options', [
            'highest' => 5.71, 'common' => 3.84, 'common_bills' => 12, 'lowest' => 1.2, 'bills_count' => 30,
        ]);

    $customer = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true])->id]);

    $this->actingAs($customer)->getJson(route('hs.code.search', ['q' => '7901']))
        ->assertOk()
        ->assertJsonMissingPath('0.reference');
});

test('a quotation keeps the reference its declared value was worked out from', function () {
    $customer = Contact::factory()->customer()->create();

    $this->actingAs($this->admin)->post(route('quotation.store'), [
        'customer_id' => $customer->id,
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 100,
        'items' => [[
            'package_quantity' => 2, 'cbm' => 2, 'net_weight' => 1000,
            'declared_value' => 696620, // 5.71 USD/kg × 1,000 kg × ৳122
            'reference_unit_price' => 5.71,
            'reference_rate_date' => '2026-10-01T00:00:00.000000Z',
            'reference_usd_rate' => 122,
        ]],
    ])->assertSessionHasNoErrors();

    $item = Quotation::sole()->items()->sole();
    expect((float) $item->reference_unit_price)->toBe(5.71)
        ->and($item->reference_rate_date->toDateString())->toBe('2026-10-01')
        ->and((float) $item->reference_usd_rate)->toBe(122.0);

    $this->actingAs($this->admin)->get(route('quotation.show', $item->quotation_id))
        ->assertOk()
        ->assertSee('ref. 5.71 USD/kg × ৳122')
        ->assertSee('01 Oct 2026');
});

test('a quotation line keeps which suggested price it used and the three it was offered', function () {
    $customer = Contact::factory()->customer()->create();
    $options = ['highest' => 5.71, 'common' => 3.84, 'common_bills' => 12, 'lowest' => 1.2, 'bills_count' => 30];

    $this->actingAs($this->admin)->post(route('quotation.store'), [
        'customer_id' => $customer->id,
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 100,
        'items' => [[
            'package_quantity' => 2, 'cbm' => 2, 'net_weight' => 1000,
            'declared_value' => 468480, // 3.84 USD/kg × 1,000 kg × ৳122
            'reference_unit_price' => 3.84,
            'reference_basis' => 'common',
            'reference_options' => json_encode($options + ['note' => '<script>']),
            'reference_rate_date' => '2026-10-01',
            'reference_usd_rate' => 122,
        ]],
    ])->assertSessionHasNoErrors();

    $item = Quotation::sole()->items()->sole();
    expect($item->reference_basis)->toBe('common')
        ->and($item->reference_options)->toBe($options); // anything else sent is dropped

    $this->get(route('quotation.show', $item->quotation_id))
        ->assertOk()
        ->assertSee('ref. most common 3.84 USD/kg × ৳122');

    // Editing the quotation offers the same three again.
    $this->get(route('quotation.edit', $item->quotation_id))
        ->assertOk()
        ->assertSee('"reference_basis":"common"', false)
        ->assertSee('"reference_options":{"highest":5.71,"common":3.84,"common_bills":12,"lowest":1.2,"bills_count":30}', false);
});

test('a quotation line refuses a basis that is not one of the suggestions', function () {
    $this->actingAs($this->admin)->post(route('quotation.store'), [
        'customer_id' => Contact::factory()->customer()->create()->id,
        'items' => [['package_quantity' => 1, 'cbm' => 1, 'reference_unit_price' => 2, 'reference_basis' => 'average']],
    ])->assertSessionHasErrors('items.0.reference_basis');
});

test('the quotation form carries today\'s dollar rate for filling declared values', function () {
    ExchangeRate::factory()->create(['rate_date' => now()->subDays(3)->toDateString(), 'usd_rate' => 121]);
    ExchangeRate::factory()->create(['rate_date' => ExchangeRate::today(), 'usd_rate' => 122.5]);

    $this->actingAs($this->admin)->get(route('quotations.create'))
        ->assertOk()
        ->assertSee('const DOLLAR_RATE = 122.5;', false)
        ->assertSee('const DOLLAR_RATE_DATE = "'.ExchangeRate::today().'";', false);
});

test('deleting a rate removes its PDF once no other rate uses it', function () {
    $this->actingAs($this->admin)->post(route('rates.store'), ['rate_date' => '2026-10-02', 'reports' => [fakeValuationReport()]]);
    $rate = ValuationRate::sole();

    $this->actingAs($this->admin)->delete(route('rates.delete', $rate->id))->assertSessionHas('success');

    expect(ValuationRate::count())->toBe(0);
    Storage::disk('local')->assertMissing($rate->file_path);
});

test('rates are only open to those allowed', function () {
    $clerk = userWithPermissions(['dashboard.view', 'quotations.view']);
    $rate = ValuationRate::factory()->create();

    $this->actingAs($clerk)->get(route('rates.index'))->assertForbidden();
    $this->actingAs($clerk)->get(route('rates.show', $rate->id))->assertForbidden();
    $this->actingAs($clerk)->post(route('rates.store'), ['rate_date' => '2026-10-02', 'reports' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertForbidden();
    $this->actingAs($clerk)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-02', 'usd_rate' => 1])->assertForbidden();
});
