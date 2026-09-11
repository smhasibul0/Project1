<?php

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = adminUser();
    $this->vehicles = AssetCategory::create([
        'name' => 'Vehicles',
        'default_method' => 'reducing_balance',
        'default_rate' => 20,
    ]);
});

/**
 * A straight-line asset costing 120,000 with no salvage over 10 years —
 * 1,000 a month, which keeps the arithmetic in these tests obvious.
 */
function straightLineAsset(array $overrides = []): Asset
{
    return Asset::create(array_merge([
        'asset_code' => Asset::nextCode(),
        'name' => 'Office Desk',
        'purchase_date' => '2026-01-01',
        'purchase_cost' => 120000,
        'salvage_value' => 0,
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 10,
        'status' => 'in_use',
    ], $overrides));
}

test('an asset code runs in sequence', function () {
    expect(Asset::nextCode())->toBe('AST-0001');

    straightLineAsset();
    expect(Asset::nextCode())->toBe('AST-0002');
});

test('straight line spreads the depreciable amount evenly over the life', function () {
    $asset = straightLineAsset();

    // (120,000 - 0) / 10 years / 12 months.
    expect($asset->monthlyCharge(Carbon::parse('2026-03-01')))->toEqual(1000.0);
});

test('salvage value is left out of the depreciable amount', function () {
    $asset = straightLineAsset(['purchase_cost' => 130000, 'salvage_value' => 10000]);

    expect($asset->depreciableBase())->toEqual(120000.0);
    expect($asset->monthlyCharge(Carbon::parse('2026-03-01')))->toEqual(1000.0);
});

test('reducing balance charges a share of what the asset is still worth', function () {
    $asset = straightLineAsset([
        'depreciation_method' => 'reducing_balance',
        'useful_life_years' => null,
        'depreciation_rate' => 20,
    ]);

    // 120,000 * 20% / 12 = 2,000 in the first month.
    expect($asset->monthlyCharge(Carbon::parse('2026-03-01')))->toEqual(2000.0);

    $asset->depreciations()->create(['period' => '2026-03-01', 'amount' => 2000, 'book_value_after' => 118000]);

    // The next month is charged on the lower book value: 118,000 * 20% / 12.
    expect(round($asset->fresh('depreciations')->monthlyCharge(Carbon::parse('2026-04-01')), 2))->toEqual(1966.67);
});

test('an asset that does not depreciate is never charged', function () {
    $land = straightLineAsset(['name' => 'Land', 'depreciation_method' => 'none', 'useful_life_years' => null]);

    expect($land->monthlyCharge(Carbon::parse('2026-03-01')))->toEqual(0.0);
});

test('nothing is charged for a month before the asset was bought', function () {
    $asset = straightLineAsset(['purchase_date' => '2026-06-15']);

    expect($asset->monthlyCharge(Carbon::parse('2026-05-01')))->toEqual(0.0);
    // The month it was bought in is charged in full.
    expect($asset->monthlyCharge(Carbon::parse('2026-06-01')))->toEqual(1000.0);
});

test('the last month tops up to the salvage value instead of overshooting', function () {
    $asset = straightLineAsset(['purchase_cost' => 10000, 'salvage_value' => 2000, 'useful_life_years' => 1]);
    // 8,000 over 12 months is 666.67 a month; 11 months leaves 333.63 to go.
    $asset->depreciations()->create(['period' => '2026-01-01', 'amount' => 7333.37, 'book_value_after' => 2666.63]);

    $charge = $asset->fresh('depreciations')->monthlyCharge(Carbon::parse('2026-12-01'));

    expect($charge)->toEqual(666.63);
    expect(round($asset->fresh('depreciations')->bookValue() - $charge, 2))->toEqual(2000.0);
});

test('a fully depreciated asset stops taking a charge', function () {
    $asset = straightLineAsset(['purchase_cost' => 12000, 'salvage_value' => 0, 'useful_life_years' => 1]);
    $asset->depreciations()->create(['period' => '2026-01-01', 'amount' => 12000, 'book_value_after' => 0]);

    $asset = $asset->fresh('depreciations');
    expect($asset->isFullyDepreciated())->toBeTrue();
    expect($asset->monthlyCharge(Carbon::parse('2026-02-01')))->toEqual(0.0);
});

test('book value is cost less everything charged', function () {
    $asset = straightLineAsset();
    $asset->depreciations()->create(['period' => '2026-01-01', 'amount' => 1000, 'book_value_after' => 119000]);
    $asset->depreciations()->create(['period' => '2026-02-01', 'amount' => 1000, 'book_value_after' => 118000]);

    $asset = $asset->fresh('depreciations');
    expect($asset->accumulatedDepreciation())->toEqual(2000.0);
    expect($asset->bookValue())->toEqual(118000.0);
    expect($asset->depreciatedPercent())->toEqual(1.67);
});

test('an asset can be added through the register', function () {
    $this->actingAs($this->user)->post(route('asset.store'), [
        'name' => 'Delivery Van',
        'asset_category_id' => $this->vehicles->id,
        'purchase_date' => '2026-02-01',
        'purchase_cost' => 1500000,
        'salvage_value' => 300000,
        'depreciation_method' => 'reducing_balance',
        'depreciation_rate' => 20,
        'location' => 'Head office',
    ])->assertSessionHasNoErrors();

    $asset = Asset::firstOrFail();
    expect($asset->asset_code)->toBe('AST-0001');
    expect($asset->name)->toBe('Delivery Van');
    expect($asset->status)->toBe('in_use');
    // A reducing-balance asset carries a rate, never a useful life.
    expect($asset->useful_life_years)->toBeNull();
    expect($asset->depreciation_rate)->toEqual('20.00');
});

test('straight line needs a useful life and reducing balance needs a rate', function () {
    $base = [
        'name' => 'Laptop',
        'purchase_date' => '2026-02-01',
        'purchase_cost' => 90000,
    ];

    $this->actingAs($this->user)
        ->post(route('asset.store'), $base + ['depreciation_method' => 'straight_line'])
        ->assertSessionHasErrors('useful_life_years');

    $this->actingAs($this->user)
        ->post(route('asset.store'), $base + ['depreciation_method' => 'reducing_balance'])
        ->assertSessionHasErrors('depreciation_rate');

    expect(Asset::count())->toBe(0);
});

test('salvage value cannot reach the purchase cost', function () {
    $this->actingAs($this->user)->post(route('asset.store'), [
        'name' => 'Laptop',
        'purchase_date' => '2026-02-01',
        'purchase_cost' => 90000,
        'salvage_value' => 90000,
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 3,
    ])->assertSessionHasErrors('salvage_value');
});

test('an asset cost cannot be edited below what has already been depreciated', function () {
    $asset = straightLineAsset();
    $asset->depreciations()->create(['period' => '2026-01-01', 'amount' => 1000, 'book_value_after' => 119000]);

    $this->actingAs($this->user)->put(route('asset.update', $asset->id), [
        'name' => 'Office Desk',
        'purchase_date' => '2026-01-01',
        'purchase_cost' => 500,
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 10,
    ])->assertSessionHasErrors('purchase_cost');

    expect($asset->fresh()->purchase_cost)->toEqual('120000.00');
});

test('posting a month charges every asset on the books once', function () {
    $desk = straightLineAsset();
    $van = straightLineAsset(['name' => 'Van', 'asset_code' => 'AST-0002', 'purchase_cost' => 240000]);

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03'])
        ->assertSessionHasNoErrors();

    expect(AssetDepreciation::count())->toBe(2);
    expect(round((float) AssetDepreciation::sum('amount'), 2))->toEqual(3000.0);
    expect($desk->fresh('depreciations')->bookValue())->toEqual(119000.0);
    expect($van->fresh('depreciations')->bookValue())->toEqual(238000.0);
    // Every entry lands on the first of the month it charges.
    expect(AssetDepreciation::pluck('period')->every(fn ($p) => $p->toDateString() === '2026-03-01'))->toBeTrue();
});

test('posting the same month twice does not double charge', function () {
    straightLineAsset();

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    expect(AssetDepreciation::count())->toBe(1);
});

test('a disposed asset stops being depreciated', function () {
    $asset = straightLineAsset();

    $this->actingAs($this->user)->post(route('asset.dispose', $asset->id), [
        'status' => 'disposed',
        'disposed_on' => '2026-02-20',
        'disposal_amount' => 90000,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    expect(AssetDepreciation::count())->toBe(0);
    expect($asset->fresh()->status)->toBe('disposed');
    expect($asset->fresh()->disposal_amount)->toEqual('90000.00');
});

test('a disposed asset can be put back on the books', function () {
    $asset = straightLineAsset(['status' => 'disposed', 'disposed_on' => '2026-02-20', 'disposal_amount' => 90000]);

    $this->actingAs($this->user)->post(route('asset.restore', $asset->id))->assertSessionHasNoErrors();

    $asset = $asset->fresh();
    expect($asset->status)->toBe('in_use');
    expect($asset->disposed_on)->toBeNull();
    expect($asset->disposal_amount)->toBeNull();
});

test('reversing an entry puts the book value back', function () {
    $asset = straightLineAsset();
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);
    expect($asset->fresh('depreciations')->bookValue())->toEqual(119000.0);

    $entry = AssetDepreciation::firstOrFail();
    $this->actingAs($this->user)->delete(route('asset.depreciation.delete', $entry->id))->assertSessionHasNoErrors();

    expect($asset->fresh('depreciations')->bookValue())->toEqual(120000.0);
    expect(AssetDepreciation::count())->toBe(0);
});

test('a whole month can be reversed in one go', function () {
    straightLineAsset();
    straightLineAsset(['name' => 'Van', 'asset_code' => 'AST-0002']);

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-04']);
    expect(AssetDepreciation::count())->toBe(4);

    $this->actingAs($this->user)->delete(route('asset.depreciation.month.delete'), ['month' => '2026-04'])
        ->assertSessionHasNoErrors();

    // April is gone, March is untouched.
    expect(AssetDepreciation::count())->toBe(2);
    expect(AssetDepreciation::pluck('period')->every(fn ($p) => $p->toDateString() === '2026-03-01'))->toBeTrue();
});

test('deleting an asset takes its depreciation history with it', function () {
    $asset = straightLineAsset();
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    $this->actingAs($this->user)->delete(route('asset.delete', $asset->id))->assertSessionHasNoErrors();

    expect(Asset::count())->toBe(0);
    expect(AssetDepreciation::count())->toBe(0);
});

test('the register totals cost, depreciation and book value across the books', function () {
    straightLineAsset();
    straightLineAsset(['name' => 'Van', 'asset_code' => 'AST-0002', 'purchase_cost' => 240000]);
    // Disposed assets are off the books, so they leave the totals.
    straightLineAsset(['name' => 'Old Printer', 'asset_code' => 'AST-0003', 'purchase_cost' => 50000, 'status' => 'disposed']);

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    $response = $this->actingAs($this->user)->get(route('assets.index'));

    $response->assertOk();
    expect($response->viewData('totalCost'))->toEqual(360000.0);
    expect($response->viewData('accumulated'))->toEqual(3000.0);
    expect($response->viewData('bookValue'))->toEqual(357000.0);
    expect($response->viewData('onBooksCount'))->toBe(2);
    expect($response->viewData('disposedCount'))->toBe(1);
});

test('the depreciation page shows what is posted and what is still waiting', function () {
    straightLineAsset();
    straightLineAsset(['name' => 'Van', 'asset_code' => 'AST-0002', 'purchase_cost' => 240000]);

    $response = $this->actingAs($this->user)->get(route('asset.depreciation', ['month' => '2026-03']));
    $response->assertOk();
    expect($response->viewData('pending'))->toHaveCount(2);
    expect($response->viewData('pendingTotal'))->toEqual(3000.0);
    expect($response->viewData('postedTotal'))->toEqual(0.0);

    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    $response = $this->actingAs($this->user)->get(route('asset.depreciation', ['month' => '2026-03']));
    expect($response->viewData('pending'))->toHaveCount(0);
    expect($response->viewData('postedTotal'))->toEqual(3000.0);
    expect($response->viewData('bookValue'))->toEqual(357000.0);
});

test('the month falls back to the current one when the parameter is junk', function () {
    $response = $this->actingAs($this->user)->get(route('asset.depreciation', ['month' => 'not-a-month']));

    $response->assertOk();
    expect($response->viewData('month')->format('Y-m'))->toBe(now()->format('Y-m'));
});

test('a category hands its depreciation defaults to the register', function () {
    $this->actingAs($this->user)->post(route('asset.category.store'), [
        'name' => 'IT Equipment',
        'default_method' => 'straight_line',
        'default_useful_life_years' => 3,
        'default_rate' => 25,
    ])->assertSessionHasNoErrors();

    $category = AssetCategory::where('name', 'IT Equipment')->firstOrFail();
    expect($category->default_useful_life_years)->toBe(3);
    // A straight-line category never keeps a reducing-balance rate.
    expect($category->default_rate)->toBeNull();
    expect($category->is_active)->toBeTrue();
});

test('deleting a category leaves its assets in the register', function () {
    $asset = straightLineAsset(['asset_category_id' => $this->vehicles->id]);

    $this->actingAs($this->user)->delete(route('asset.category.delete', $this->vehicles->id))
        ->assertSessionHasNoErrors();

    expect(Asset::count())->toBe(1);
    expect($asset->fresh()->asset_category_id)->toBeNull();
});

test('depreciation stays out of the P&L', function () {
    straightLineAsset();
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-09']);

    $response = $this->actingAs($this->user)->get(route('reports.profit-loss', [
        'from' => '2026-09-01',
        'to' => '2026-09-30',
    ]));

    $response->assertOk();
    // The asset module is deliberately kept separate from the P&L for now, so
    // a posted month changes neither operating expenses nor net profit.
    $operating = $response->viewData('operating');
    expect($operating)->not->toHaveKey('depreciation');
    expect($operating['total'])->toEqual(0.0);
    expect($response->viewData('netProfit'))->toEqual(0.0);
    $response->assertDontSee('Less: Depreciation');
});

test('the asset module is closed to users without the permission', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('assets.index'))->assertForbidden();
    $this->actingAs($outsider)->get(route('asset.depreciation'))->assertForbidden();
    $this->actingAs($outsider)->get(route('asset.categories'))->assertForbidden();
});

test('every asset page renders for someone who can manage assets', function () {
    straightLineAsset(['asset_category_id' => $this->vehicles->id]);
    $this->actingAs($this->user)->post(route('asset.depreciation.generate'), ['month' => '2026-03']);

    foreach (['assets.index', 'asset.depreciation', 'asset.categories'] as $route) {
        $this->actingAs($this->user)->get(route($route))->assertOk();
    }
});
