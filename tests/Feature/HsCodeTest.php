<?php

use App\Models\HsCode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('importing the tariff book rebuilds each line from its heading', function () {
    $response = $this->actingAs($this->user)
        ->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    $response->assertRedirect();
    expect(HsCode::count())->toBe(5);

    $line = HsCode::where('code_digits', '39119000')->first();
    expect($line->code)->toBe('3911.90.00');
    expect($line->description)->toBe('Petroleum resins, coumarone-indene resins, polyterpenes and other products, in primary forms — Other');
    expect($line->statistical_unit)->toBe('kg');
});

test('duty written as a fraction is stored as a percentage', function () {
    $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    // 0.15 in the book is a 15% rate; a cell already written "10%" stays at 10.
    expect((float) HsCode::where('code_digits', '39111000')->value('cd_rate'))->toEqual(15.0);
    expect((float) HsCode::where('code_digits', '85441100')->value('cd_rate'))->toEqual(25.0);
    expect((float) HsCode::where('code_digits', '85441900')->value('cd_rate'))->toEqual(10.0);
});

test('a code in the first column and two codes stacked in one cell are all read', function () {
    $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    expect(HsCode::where('code_digits', '85441100')->value('description'))
        ->toBe('Insulated wire, cable and other insulated electric conductors — Winding wire — Of copper');
    expect(HsCode::where('code_digits', '85441900')->exists())->toBeTrue();
    expect(HsCode::where('code_digits', '85442000')->value('description'))
        ->toBe('Insulated wire, cable and other insulated electric conductors — Coaxial cable');
});

test('re-importing the book updates lines instead of duplicating them', function () {
    $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);
    $response = $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '0 new'));
    expect(HsCode::count())->toBe(5);
});

test('a rates sheet tops up stored codes and skips ones it does not know', function () {
    $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    $response = $this->actingAs($this->user)->post(route('hs.code.import'), [
        'sheet' => fakeRatesSheet([
            ['3911.90.00', 0, 15, 5, 3, 7.5],
            ['9999.99.99', 0, 15, 5, 0, 7.5],
        ]),
    ]);

    $line = HsCode::where('code_digits', '39119000')->first();
    expect((float) $line->vat_rate)->toEqual(15.0);
    expect((float) $line->rd_rate)->toEqual(3.0);
    expect((float) $line->at_rate)->toEqual(7.5);
    // The tariff import's CD survives a rates sheet that doesn't carry one.
    expect((float) $line->cd_rate)->toEqual(5.0);

    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 not found'));
    expect(HsCode::count())->toBe(5);
});

test('the customs export with codes kept as numbers is imported in full', function () {
    $response = $this->actingAs($this->user)
        ->post(route('hs.code.import'), ['sheet' => fakeCustomsExportSheet()]);

    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '4 new'));
    expect(HsCode::count())->toBe(4);

    // 0101.21.00 arrives as the number 1012100 and gets its leading zero back.
    $horses = HsCode::where('code_digits', '01012100')->first();
    expect($horses->code)->toBe('0101.21.00')
        ->and($horses->description)->toBe('Pure-bred breeding animals of horses')
        ->and((float) $horses->cd_rate)->toEqual(5.0)
        ->and((float) $horses->ait_rate)->toEqual(5.0);
});

test('a specific duty in taka is not taken for a percentage', function () {
    $this->actingAs($this->user)->post(route('hs.code.import'), ['sheet' => fakeCustomsExportSheet()]);

    // Tk 3000 a tonne on sugar and Tk 1800 / Tk 600 on scrap book as zero, to be keyed by hand.
    $sugar = HsCode::where('code_digits', '17011200')->first();
    expect((float) $sugar->cd_rate)->toEqual(0.0)
        ->and((float) $sugar->rd_rate)->toEqual(15.0);

    $scrap = HsCode::where('code_digits', '72041000')->first();
    expect((float) $scrap->vat_rate)->toEqual(0.0)
        ->and((float) $scrap->ait_rate)->toEqual(0.0);

    // The 500% SD on large cars is a real rate and stays.
    $car = HsCode::where('code_digits', '87032421')->first();
    expect((float) $car->sd_rate)->toEqual(500.0)
        ->and($car->totalTaxIncidence())->toEqual(860.5);
});

test('a workbook without an HS code column is rejected', function () {
    $response = $this->actingAs($this->user)
        ->post(route('hs.code.import'), ['sheet' => fakePackingList()]);

    $response->assertSessionHas('error');
    expect(HsCode::count())->toBe(0);
});

test('the search endpoint matches a code typed with or without dots', function () {
    HsCode::factory()->create(['code' => '3911.90.00', 'description' => 'Polysulphides, in primary forms', 'vat_rate' => 15]);

    $this->actingAs($this->user)->getJson(route('hs.code.search', ['q' => '391190']))
        ->assertOk()
        ->assertJsonPath('0.code', '3911.90.00')
        ->assertJsonPath('0.vat_rate', '15.00');

    $this->actingAs($this->user)->getJson(route('hs.code.search', ['q' => '3911.90']))
        ->assertJsonCount(1);

    $this->actingAs($this->user)->getJson(route('hs.code.search', ['q' => 'polysulphides']))
        ->assertJsonCount(1);
});

test('an inactive code is never offered to a quotation', function () {
    HsCode::factory()->create(['code' => '3911.90.00', 'is_active' => false]);

    $this->actingAs($this->user)->getJson(route('hs.code.search', ['q' => '3911']))
        ->assertJsonCount(0);
});

test('the tariff list is searchable and paged', function () {
    HsCode::factory()->count(60)->create();
    HsCode::factory()->create(['code' => '1234.56.78', 'description' => 'Findable widget']);

    $this->actingAs($this->user)->get(route('hs.codes'))
        ->assertOk()
        ->assertSee('61 stored', false);

    $this->actingAs($this->user)->get(route('hs.codes', ['q' => 'Findable']))
        ->assertOk()
        ->assertSee('1234.56.78');
});

test('an HS code can be added and edited by hand', function () {
    $this->actingAs($this->user)->post(route('hs.code.store'), [
        'code' => '3911.90.00',
        'description' => 'Polysulphides',
        'cd_rate' => 5, 'vat_rate' => 15, 'ait_rate' => 5, 'at_rate' => 7.5,
        'is_active' => 1,
    ])->assertRedirect();

    $hsCode = HsCode::first();
    expect($hsCode->code_digits)->toBe('39119000');
    expect((float) $hsCode->at_rate)->toEqual(7.5);

    $this->actingAs($this->user)->put(route('hs.code.update', $hsCode->id), [
        'code' => '3911.90.00',
        'description' => 'Polysulphides and polysulphones',
        'sd_rate' => 20,
    ])->assertRedirect();

    expect((float) $hsCode->fresh()->sd_rate)->toEqual(20.0);
});

test('the same code cannot be stored twice under a different spelling', function () {
    HsCode::factory()->create(['code' => '3911.90.00']);

    $this->actingAs($this->user)->post(route('hs.code.store'), [
        'code' => '39119000',
        'description' => 'Duplicate',
    ])->assertSessionHasErrors('code_digits');

    expect(HsCode::count())->toBe(1);
});

test('total tax incidence follows the customs cascade', function () {
    // The rates from the tariff lookup screenshot: CD 5, SD 0, VAT 15, AIT 5, RD 0, AT 7.5.
    $hsCode = HsCode::factory()->make([
        'cd_rate' => 5, 'sd_rate' => 0, 'vat_rate' => 15, 'ait_rate' => 5, 'rd_rate' => 0, 'at_rate' => 7.5,
    ]);

    // On an assessable value of 100: CD 5, VAT 15% of 105 = 15.75, AIT 5, AT 7.5% of 105 = 7.875.
    expect($hsCode->totalTaxIncidence())->toEqual(33.63);
});
