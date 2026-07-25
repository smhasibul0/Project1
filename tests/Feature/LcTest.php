<?php

use App\Models\Contact;
use App\Models\Lc;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->order = Order::factory()->create();
    $this->supplier = Contact::factory()->supplier()->create();
});

test('an LC is created against an order with auto code and computed bank charges', function () {
    $response = $this->actingAs($this->user)->post(route('lc.store'), [
        'order_id' => $this->order->id,
        'supplier_id' => $this->supplier->id,
        'pi_date' => '2026-01-12',
        'pi_no' => 'RTCHW-01/2026',
        'lc_number' => '9326160005',
        'opening_bank' => 'Janata Bank',
        'container_no' => 'FFAU7873853',
        'commodity' => 'CAD 01/1/2026',
        'invoice_amount' => 12430.80,
        'currency' => 'USD',
        'net_amount_received' => 12130.80,
        'usd_sell_rate' => 120.50,
        'usd_sell_date' => '2026-04-09',
        'lc_status' => 'released',
        'released_date' => '2026-02-10',
    ]);

    $response->assertRedirect(route('lc.index'));

    $lc = Lc::firstOrFail();
    expect($lc->lc_code)->toBe('LC0001');
    expect($lc->order_id)->toBe($this->order->id);
    expect($lc->supplier_id)->toBe($this->supplier->id);
    // Bank charges auto = invoice_amount - net_amount_received
    expect($lc->bank_charges)->toEqual('300.00');
    expect($lc->lc_status)->toBe('released');
});

test('bank charges are zero when no net amount received is entered', function () {
    $this->actingAs($this->user)->post(route('lc.store'), [
        'order_id' => $this->order->id,
        'invoice_amount' => 5000,
    ]);

    expect(Lc::firstOrFail()->bank_charges)->toEqual('0.00');
});

test('an LC can be created standalone without an order', function () {
    $response = $this->actingAs($this->user)->post(route('lc.store'), [
        'supplier_id' => $this->supplier->id,
        'invoice_amount' => 5000,
        'net_amount_received' => 4900,
    ]);

    $response->assertRedirect(route('lc.index'));

    $lc = Lc::firstOrFail();
    expect($lc->order_id)->toBeNull();
    expect($lc->bank_charges)->toEqual('100.00');
});

test('deleting an order releases its LCs instead of deleting them', function () {
    $lc = Lc::factory()->create(['order_id' => $this->order->id]);

    $this->order->delete();

    expect($lc->fresh())->not->toBeNull();
    expect($lc->fresh()->order_id)->toBeNull();
});

test('linking a standalone LC to an order folds its charges into that order', function () {
    $lc = Lc::factory()->create(['order_id' => null, 'invoice_amount' => 1000, 'net_amount_received' => 900, 'bank_charges' => 100]);

    $this->actingAs($this->user)->put(route('lc.update', $lc->id), [
        'order_id' => $this->order->id,
        'invoice_amount' => 1000,
        'net_amount_received' => 900,
    ]);

    expect($lc->fresh()->order_id)->toBe($this->order->id);
    expect((float) $this->order->fresh()->lc_cost)->toEqual(100.0);

    // Unlinking pulls the charges back out of the order.
    $this->actingAs($this->user)->put(route('lc.update', $lc->id), [
        'order_id' => null,
        'invoice_amount' => 1000,
        'net_amount_received' => 900,
    ]);

    expect($lc->fresh()->order_id)->toBeNull();
    expect((float) $this->order->fresh()->lc_cost)->toEqual(0.0);
});

test('a PI document can be attached to an LC', function () {
    Storage::fake();

    $this->actingAs($this->user)->post(route('lc.store'), [
        'order_id' => $this->order->id,
        'invoice_amount' => 5000,
        'pi_document' => UploadedFile::fake()->create('pi.pdf', 100, 'application/pdf'),
    ]);

    $lc = Lc::firstOrFail();
    expect($lc->pi_document)->not->toBeNull();
    expect(file_exists(public_path('upload/lc/'.$lc->pi_document)))->toBeTrue();

    @unlink(public_path('upload/lc/'.$lc->pi_document));
});

test('an LC can be updated and recomputes bank charges', function () {
    $lc = Lc::factory()->create([
        'order_id' => $this->order->id,
        'invoice_amount' => 10000,
        'net_amount_received' => 9700,
        'bank_charges' => 300,
    ]);

    $this->actingAs($this->user)->put(route('lc.update', $lc->id), [
        'order_id' => $this->order->id,
        'invoice_amount' => 10000,
        'net_amount_received' => 9670,
        'lc_status' => 'settled',
    ]);

    $lc->refresh();
    expect($lc->bank_charges)->toEqual('330.00');
    expect($lc->lc_status)->toBe('settled');
});

test('the LC status can be updated from the view page and stamps the released date', function () {
    $lc = Lc::factory()->create(['order_id' => $this->order->id, 'lc_status' => 'draft', 'released_date' => null]);

    $this->actingAs($this->user)->post(route('lc.status', $lc->id), [
        'lc_status' => 'released',
    ])->assertRedirect()->assertSessionHas('success');

    $lc->refresh();
    expect($lc->lc_status)->toBe('released');
    expect($lc->released_date->toDateString())->toBe(now()->toDateString());
});

test('an LC can be deleted', function () {
    $lc = Lc::factory()->create(['order_id' => $this->order->id]);

    $this->actingAs($this->user)->delete(route('lc.delete', $lc->id))
        ->assertRedirect()->assertSessionHas('success');

    expect(Lc::count())->toBe(0);
});

test('LC bank charges and charge lines feed the linked order cost and profit', function () {
    // Order with revenue 500, supplier cost 300.
    $this->order->items()->create([
        'item_description' => 'Toys', 'quantity' => 5,
        'our_asking_price' => 100, 'supplier_asking_price' => 60, 'line_total' => 500,
    ]);

    // LC with 300 bank charges (invoice 12000, received 11700).
    $this->actingAs($this->user)->post(route('lc.store'), [
        'order_id' => $this->order->id,
        'invoice_amount' => 12000,
        'net_amount_received' => 11700,
    ]);
    $lc = Lc::firstOrFail();

    $this->order->refresh();
    expect($this->order->lc_cost)->toEqual('300.00');
    // profit 500 - 300 supplier - 0 order costs - 300 lc = -100
    expect($this->order->profit)->toEqual('-100.00');

    // Add an LC charge line of 200.
    $this->actingAs($this->user)->post(route('lc.cost.store', $lc->id), [
        'title' => 'LC opening charge',
        'amount' => 200,
    ])->assertSessionHas('success');

    $this->order->refresh();
    expect($this->order->lc_cost)->toEqual('500.00'); // 300 bank + 200 charge
    expect($this->order->profit)->toEqual('-300.00');
});

test('the LC list and create pages load', function () {
    $this->actingAs($this->user)->get(route('lc.index'))->assertOk();
    $this->actingAs($this->user)->get(route('lc.create'))->assertOk()->assertSee('Add LC');
    $this->actingAs($this->user)->get(route('lc.create', ['order_id' => $this->order->id]))->assertOk();
});

test('standalone LC charges reduce the P&L net profit as an operating expense', function () {
    $lc = Lc::factory()->create([
        'order_id' => null,
        'invoice_amount' => 2000,
        'net_amount_received' => 1850,
        'bank_charges' => 150,
    ]);
    $lc->costs()->create(['title' => 'Swift charge', 'amount' => 50, 'cost_date' => now()->toDateString()]);

    $response = $this->actingAs($this->user)->get(route('reports.profit-loss'));
    $response->assertOk();

    $operating = $response->viewData('operating');
    expect($operating['standalone_lc'])->toEqual(200.0); // 150 bank charges + 50 charge line
    expect($operating['total'])->toEqual(200.0);
    expect($response->viewData('netProfit'))->toEqual(-200.0);
});
