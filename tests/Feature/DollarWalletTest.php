<?php

use App\Models\DollarTransaction;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Support\DollarLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->bank = PaymentAccount::factory()->create(['name' => 'Janata Bank', 'balance' => 100000]);
    $this->other = PaymentAccount::factory()->create(['name' => 'City Bank', 'balance' => 0]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function depositDollars(PaymentAccount $account, float $usd, float $rate, array $overrides = []): TestResponse
{
    return test()->post(route('dollars.deposit', $account->id), array_merge([
        'usd_amount' => $usd, 'rate' => $rate, 'entry_date' => '2026-10-01',
    ], $overrides));
}

test('dollars paid in are held at the rate given', function () {
    $this->actingAs($this->user);

    depositDollars($this->bank, 1000, 120, ['reference' => 'TT-9', 'note' => 'Customer paid in dollars'])->assertSessionHas('success');

    $account = $this->bank->fresh();
    expect($account->usd_balance)->toEqual('1000.00')
        ->and($account->usd_cost)->toEqual('120000.00')
        ->and($account->balance)->toEqual('100000.00'); // no taka moves

    expect(DollarTransaction::sole())->direction->toBe('in')->source->toBe('deposit')->reference->toBe('TT-9');
});

test('the dollars held are costed at the average of what they cost', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 120);
    depositDollars($this->bank, 3000, 124);

    // (120,000 + 372,000) / 4,000
    expect($this->bank->fresh()->averageUsdRate())->toEqual(123.0);

    $taka = DB::transaction(fn () => DollarLedger::spend($this->bank, 500, 'order_cost'));
    expect($taka->bdt_amount)->toEqual('61500.00')->and($taka->rate)->toEqual('123.0000');

    // Spending leaves the average where it was.
    $account = $this->bank->fresh();
    expect($account->usd_balance)->toEqual('3500.00')->and($account->averageUsdRate())->toEqual(123.0);
});

test('spending every dollar takes all of their cost, leaving nothing stranded', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 3, 120.3333);

    $out = DB::transaction(fn () => DollarLedger::spend($this->bank, 3, 'order_cost'));

    expect($out->bdt_amount)->toEqual('361.00');
    expect($this->bank->fresh())->usd_balance->toEqual('0.00')->usd_cost->toEqual('0.00');
});

test('more dollars than the account holds cannot be spent', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 100, 120);

    expect(fn () => DB::transaction(fn () => DollarLedger::spend($this->bank, 100.01, 'order_cost')))
        ->toThrow(ValidationException::class, 'Janata Bank holds $100.00 — $100.01 are needed.');
});

test('selling dollars brings in taka and books the gain against what they cost', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 10000, 122);

    $this->post(route('dollars.sell', $this->bank->id), [
        'usd_amount' => 6500, 'rate' => 124, 'entry_date' => '2026-10-05', 'reference' => 'SALE-1',
    ])->assertSessionHas('success', 'Sold $6,500.00 for ৳806,000.00 — exchange gain ৳13,000.00.');

    $account = $this->bank->fresh();
    expect($account->balance)->toEqual('906000.00') // 100,000 + 806,000
        ->and($account->usd_balance)->toEqual('3500.00')
        ->and($account->usd_cost)->toEqual('427000.00');

    $sale = DollarTransaction::where('source', 'sale')->sole();
    expect($sale->gain_loss)->toEqual('13000.00')->and($sale->bdt_amount)->toEqual('793000.00');

    $taka = Transaction::where('source', 'dollar_sale')->sole();
    expect($taka->credit)->toEqual('806000.00')->and($taka->usd_amount)->toEqual('6500.00')->and($taka->created_at->toDateString())->toBe('2026-10-05');
});

test('selling below cost is a loss', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);

    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 1000, 'rate' => 121, 'entry_date' => '2026-10-05'])
        ->assertSessionHas('success', 'Sold $1,000.00 for ৳121,000.00 — exchange loss ৳1,000.00.');

    expect(DollarTransaction::where('source', 'sale')->value('gain_loss'))->toEqual('-1000.00');
});

test('the P&L counts the gain or loss on dollars sold in the range', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 2000, 122);
    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 1000, 'rate' => 124, 'entry_date' => '2026-10-05']); // +2,000
    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 1000, 'rate' => 121.5, 'entry_date' => '2026-11-05']); // −500

    expect($this->get(route('reports.profit-loss'))->viewData('exchangeGainLoss'))->toEqual(1500.0);

    $october = $this->get(route('reports.profit-loss', ['from' => '2026-10-01', 'to' => '2026-10-31']));
    expect($october->viewData('exchangeGainLoss'))->toEqual(2000.0);
    expect($october->viewData('netProfit'))->toEqual(round($october->viewData('totals')['profit'] - $october->viewData('operating')['total'] + 2000, 2));
});

test('dollars move between accounts at what they cost', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);

    $this->post(route('dollars.transfer', $this->bank->id), [
        'to_account_id' => $this->other->id, 'usd_amount' => 400, 'entry_date' => '2026-10-02',
    ])->assertSessionHas('success');

    expect($this->bank->fresh())->usd_balance->toEqual('600.00')->usd_cost->toEqual('73200.00');
    expect($this->other->fresh())->usd_balance->toEqual('400.00')->usd_cost->toEqual('48800.00');
    expect(DollarTransaction::where('source', 'transfer')->pluck('transfer_group')->unique())->toHaveCount(1);

    // Not to itself.
    $this->post(route('dollars.transfer', $this->bank->id), ['to_account_id' => $this->bank->id, 'usd_amount' => 1, 'entry_date' => '2026-10-02'])
        ->assertSessionHasErrors('to_account_id');
});

test('a sale is reversed with its taka, and a transfer on both sides', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);
    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 500, 'rate' => 124, 'entry_date' => '2026-10-05']);
    $this->post(route('dollars.transfer', $this->bank->id), ['to_account_id' => $this->other->id, 'usd_amount' => 200, 'entry_date' => '2026-10-06']);

    $this->delete(route('dollars.delete', DollarTransaction::where('source', 'sale')->value('id')))->assertSessionHas('success');
    $this->delete(route('dollars.delete', DollarTransaction::where('source', 'transfer')->where('direction', 'in')->value('id')))->assertSessionHas('success');

    expect($this->bank->fresh())->balance->toEqual('100000.00')->usd_balance->toEqual('1000.00')->usd_cost->toEqual('122000.00');
    expect($this->other->fresh())->usd_balance->toEqual('0.00');
    expect(Transaction::where('source', 'dollar_sale')->count())->toBe(0);
    expect(DollarTransaction::count())->toBe(1);
});

test('a deposit whose dollars were spent cannot be reversed', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);
    DB::transaction(fn () => DollarLedger::spend($this->bank, 600, 'order_cost'));

    $this->delete(route('dollars.delete', DollarTransaction::where('source', 'deposit')->value('id')))
        ->assertSessionHasErrors('usd_amount');

    expect($this->bank->fresh()->usd_balance)->toEqual('400.00');
});

test('dollars spent on a record are undone from the record, not the account', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);
    $spent = DB::transaction(fn () => DollarLedger::spend($this->bank, 100, 'order_cost'));

    $this->delete(route('dollars.delete', $spent->id))->assertSessionHas('error');

    expect(DollarTransaction::count())->toBe(2);
});

test('the account book shows the dollars, their history and what can be done with them', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122, ['note' => 'From the Dubai agent']);
    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 250, 'rate' => 124, 'entry_date' => '2026-10-05']);

    $this->get(route('payment.account.book', $this->bank->id))
        ->assertOk()
        ->assertSee('Deposit Dollars')->assertSee('Sell for Taka')->assertSee('Transfer Dollars')
        ->assertSee('From the Dubai agent')
        ->assertSee('Sold for taka')
        ->assertSee('500.00') // the gain: 250 × (124 − 122)
        ->assertSeeInOrder(['$750.00', '$1,000.00']); // balance after each, newest first
});

test('dollar actions each need their own permission', function () {
    $clerk = userWithPermissions(['accounts.view']);
    $this->actingAs($clerk);

    depositDollars($this->bank, 100, 122)->assertForbidden();
    $this->post(route('dollars.sell', $this->bank->id), ['usd_amount' => 1, 'rate' => 1, 'entry_date' => '2026-10-05'])->assertForbidden();
    $this->post(route('dollars.transfer', $this->bank->id), ['to_account_id' => $this->other->id, 'usd_amount' => 1, 'entry_date' => '2026-10-05'])->assertForbidden();

    $this->get(route('payment.account.book', $this->bank->id))->assertOk()
        ->assertDontSee('Deposit Dollars')->assertDontSee('Sell for Taka');
});

test('the balance sheet counts the dollars held at what they cost', function () {
    $this->actingAs($this->user);
    depositDollars($this->bank, 1000, 122);

    $response = $this->get(route('reports.balance-sheet'))->assertOk();

    expect($response->viewData('dollarsHeld'))->toEqual(1000.0);
    expect($response->viewData('dollarsAtCost'))->toEqual(122000.0);
    expect($response->viewData('cashBank'))->toEqual(222000.0); // ৳100,000 + $1,000 at ৳122
});
