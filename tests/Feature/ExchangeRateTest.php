<?php

use App\Models\ActivityLog;
use App\Models\ExchangeRate;

beforeEach(function () {
    $this->admin = adminUser();
});

test('a day without its own rate uses the latest one before it', function () {
    ExchangeRate::factory()->create(['rate_date' => '2026-10-01', 'usd_rate' => 121]);
    ExchangeRate::factory()->create(['rate_date' => '2026-10-04', 'usd_rate' => 122.5]);

    expect(ExchangeRate::forDate('2026-09-30'))->toBeNull()
        ->and((float) ExchangeRate::forDate('2026-10-01')->usd_rate)->toBe(121.0)
        ->and((float) ExchangeRate::forDate('2026-10-03')->usd_rate)->toBe(121.0)
        ->and((float) ExchangeRate::forDate('2026-10-04')->usd_rate)->toBe(122.5)
        ->and((float) ExchangeRate::forDate('2026-10-09')->usd_rate)->toBe(122.5)
        ->and(ExchangeRate::forDate('2026-10-09')->isFor('2026-10-09'))->toBeFalse();
});

test('setting a day\'s rate again replaces it instead of adding a second one', function () {
    $this->actingAs($this->admin)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-06', 'usd_rate' => 122.5])
        ->assertRedirect(route('exchange.rates'));
    $this->actingAs($this->admin)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-06', 'usd_rate' => 122.8, 'note' => 'Bank rate'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'changed'));

    $rate = ExchangeRate::sole();
    expect((float) $rate->usd_rate)->toBe(122.8)
        ->and($rate->note)->toBe('Bank rate')
        ->and($rate->added_by)->toBe($this->admin->id);

    // Both saves are in the activity log: added, then edited from 122.50 to 122.80.
    expect(ActivityLog::where('subject_type', $rate->getMorphClass())->pluck('action')->all())
        ->toBe([ActivityLog::ADDED, ActivityLog::EDITED]);
});

test('a rate can be edited, but not moved onto a day that has its own', function () {
    $first = ExchangeRate::factory()->create(['rate_date' => '2026-10-01', 'usd_rate' => 121]);
    ExchangeRate::factory()->create(['rate_date' => '2026-10-02', 'usd_rate' => 121.5]);

    $this->actingAs($this->admin)->put(route('exchange.rate.update', $first->id), ['rate_date' => '2026-10-01', 'usd_rate' => 121.25])
        ->assertSessionHas('success');
    expect((float) $first->fresh()->usd_rate)->toBe(121.25);

    $this->actingAs($this->admin)->put(route('exchange.rate.update', $first->id), ['rate_date' => '2026-10-02', 'usd_rate' => 121.25])
        ->assertSessionHas('error');
    expect($first->fresh()->rate_date->toDateString())->toBe('2026-10-01');
});

test('a rate can be deleted', function () {
    $rate = ExchangeRate::factory()->create();

    $this->actingAs($this->admin)->delete(route('exchange.rate.delete', $rate->id))->assertSessionHas('success');

    expect(ExchangeRate::count())->toBe(0);
});

test('the page shows today\'s rate, or says when an earlier day\'s is standing in', function () {
    ExchangeRate::factory()->create(['rate_date' => now()->subDays(2)->toDateString(), 'usd_rate' => 121.75]);

    $this->actingAs($this->admin)->get(route('exchange.rates'))
        ->assertOk()
        ->assertSee('$1 = ৳ 121.75')
        ->assertSee('No rate set for today')
        ->assertSee('In use');

    ExchangeRate::factory()->create(['rate_date' => ExchangeRate::today(), 'usd_rate' => 122.25]);

    $this->actingAs($this->admin)->get(route('exchange.rates'))
        ->assertOk()
        ->assertSee('$1 = ৳ 122.25')
        ->assertSee('Set for today.')
        ->assertSee('0.5');
});

test('adding a rate does not let someone change an existing day without edit rights', function () {
    ExchangeRate::factory()->create(['rate_date' => '2026-10-06', 'usd_rate' => 122.5]);
    $clerk = userWithPermissions(['dashboard.view', 'exchange-rates.view', 'exchange-rates.create']);

    $this->actingAs($clerk)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-07', 'usd_rate' => 122.6])
        ->assertSessionHas('success');
    $this->actingAs($clerk)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-06', 'usd_rate' => 1])
        ->assertSessionHas('error');

    expect((float) ExchangeRate::onDate('2026-10-06')->usd_rate)->toBe(122.5);
});

test('exchange rates are only open to those allowed', function () {
    $rate = ExchangeRate::factory()->create();
    $viewer = userWithPermissions(['dashboard.view', 'exchange-rates.view'], 'viewer');
    $outsider = userWithPermissions(['dashboard.view'], 'outsider');

    $this->actingAs($outsider)->get(route('exchange.rates'))->assertForbidden();

    $this->actingAs($viewer)->get(route('exchange.rates'))->assertOk()->assertDontSee('Save Rate');
    $this->actingAs($viewer)->post(route('exchange.rate.store'), ['rate_date' => '2026-10-06', 'usd_rate' => 1])->assertForbidden();
    $this->actingAs($viewer)->put(route('exchange.rate.update', $rate->id), ['rate_date' => '2026-10-06', 'usd_rate' => 1])->assertForbidden();
    $this->actingAs($viewer)->delete(route('exchange.rate.delete', $rate->id))->assertForbidden();
});
