<?php

use App\Models\Lc;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('LCs, CADs and TTs each get their own codes', function () {
    $this->actingAs($this->user);

    foreach (['lc', 'cad', 'tt', 'cad', 'lc'] as $type) {
        $this->post(route('lc.store'), ['type' => $type, 'invoice_amount' => 1000])
            ->assertRedirect(route('lc.index', $type));
    }

    expect(Lc::orderBy('id')->pluck('lc_code')->all())
        ->toBe(['LC0001', 'CAD0001', 'TT0001', 'CAD0002', 'LC0002']);
    expect(Lc::where('lc_code', 'TT0001')->value('type'))->toBe('tt');
});

test('a record saved without a type is an LC', function () {
    $this->actingAs($this->user)->post(route('lc.store'), ['invoice_amount' => 1000]);

    expect(Lc::firstOrFail())->type->toBe('lc')->lc_code->toBe('LC0001');
});

test('an unknown type is refused', function () {
    $this->actingAs($this->user)
        ->post(route('lc.store'), ['type' => 'dp', 'invoice_amount' => 1000])
        ->assertSessionHasErrors('type');

    expect(Lc::count())->toBe(0);
});

test('each list shows only its own type', function () {
    Lc::factory()->create(['type' => 'lc', 'lc_number' => 'LC-NUM-1']);
    Lc::factory()->create(['type' => 'cad', 'lc_number' => 'CAD-NUM-1']);
    Lc::factory()->create(['type' => 'tt', 'lc_number' => 'TT-NUM-1']);
    $this->actingAs($this->user);

    $this->get(route('lc.index', 'cad'))->assertOk()
        ->assertSee('Cash Against Documents')->assertSee('Add CAD')->assertSee('CAD Number')
        ->assertSee('CAD-NUM-1')->assertDontSee('LC-NUM-1')->assertDontSee('TT-NUM-1');

    $this->get(route('lc.index', 'tt'))->assertOk()
        ->assertSee('Telegraphic Transfers')->assertSee('TT-NUM-1')->assertDontSee('CAD-NUM-1');

    // The old list address still opens the LCs.
    $this->get('/lcs')->assertOk()
        ->assertSee('Letters of Credit')->assertSee('LC-NUM-1')->assertDontSee('CAD-NUM-1');

    $this->get('/lcs/dp')->assertNotFound();
});

test('the sidebar groups LC, CAD and TT under LC Management', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            'LC Management',
            route('lc.index', 'lc'), '>LC<',
            route('lc.index', 'cad'), '>CAD<',
            route('lc.index', 'tt'), '>TT<',
        ], false);
});

test('the add page starts on the type it was opened for', function () {
    $this->actingAs($this->user);

    $this->get(route('lc.create', ['type' => 'tt']))->assertOk()
        ->assertSee('Add TT')
        ->assertSee('<option value="tt" data-label="TT" selected', false);

    // Anything else starts on LC.
    $this->get(route('lc.create', ['type' => 'dp']))->assertOk()
        ->assertSee('<option value="lc" data-label="LC" selected', false);
});

test('moving a record to another type gives it that type\'s next code', function () {
    Lc::factory()->create(['type' => 'cad']); // CAD0001
    $lc = Lc::factory()->create(['type' => 'lc', 'order_id' => null]); // LC0001

    $this->actingAs($this->user)->put(route('lc.update', $lc->id), [
        'type' => 'cad',
        'invoice_amount' => 1000,
    ])->assertRedirect(route('lc.index', 'cad'));

    expect($lc->fresh())->type->toBe('cad')->lc_code->toBe('CAD0002');
});

test('an update that leaves out the type keeps it', function () {
    $lc = Lc::factory()->create(['type' => 'tt', 'order_id' => null]);

    $this->actingAs($this->user)->put(route('lc.update', $lc->id), ['invoice_amount' => 1000]);

    expect($lc->fresh())->type->toBe('tt')->lc_code->toBe('TT0001');
});

test('a dollar CAD is paid like an LC and named as a CAD', function () {
    $account = PaymentAccount::factory()->create(['balance' => 1000000]);
    $cad = Lc::factory()->create(['type' => 'cad', 'invoice_amount' => 5000, 'currency' => 'USD']);
    $this->actingAs($this->user);

    $this->get(route('lc.show', $cad->id))->assertOk()
        ->assertSee('CAD0001')->assertSee('Cash Against Documents')
        ->assertSee('CAD Payments (0)')->assertSee('Pay CAD');

    $this->post(route('lc.payment.store', $cad->id), [
        'paid_on' => '2026-10-01',
        'payment_account_id' => $account->id,
        'usd_amount' => 5000,
        'bank_rate' => 122.5,
    ])->assertSessionHas('success');

    expect(Transaction::where('source', 'lc_payment')->value('description'))
        ->toBe('CAD payment CAD0001 — $5,000.00 @ 122.5');
    expect($account->fresh()->balance)->toEqual('387500.00');
});

test('the order page lists its LCs, CADs and TTs with their type', function () {
    $order = Order::factory()->create();
    Lc::factory()->create(['order_id' => $order->id, 'type' => 'tt']);

    $this->actingAs($this->user)->get(route('order.show', $order->id))
        ->assertOk()
        ->assertSee('LC / CAD / TT (1)')
        ->assertSee('TT0001');
});
