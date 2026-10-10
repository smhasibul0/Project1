<?php

use App\Models\DollarTransaction;
use App\Models\ExchangeRate;
use App\Models\Lc;
use App\Models\LcPayment;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Support\DollarLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    $this->order = Order::factory()->create();
    $this->account = PaymentAccount::factory()->create(['name' => 'Janata Bank', 'balance' => 5000000]);
    $this->lc = Lc::factory()->create([
        'order_id' => $this->order->id,
        'lc_code' => 'LC0005',
        'invoice_amount' => 10000,
        'currency' => 'USD',
        'net_amount_received' => 9990,
        'bank_charges' => 10,
        'usd_sell_rate' => null,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function payLc(Lc $lc, PaymentAccount $account, array $overrides = []): TestResponse
{
    return test()->post(route('lc.payment.store', $lc->id), array_merge([
        'paid_on' => '2026-10-01',
        'payment_account_id' => $account->id,
        'usd_amount' => 4000,
        'bank_rate' => 123.1,
        'reference' => 'ADV-001',
    ], $overrides));
}

test('paying a dollar LC takes the taka at the bank rate and keeps the dollars in the account', function () {
    $this->actingAs($this->user);

    payLc($this->lc, $this->account)->assertSessionHas('success');

    $payment = LcPayment::firstOrFail();
    expect($payment->bdt_amount)->toEqual('492400.00'); // $4,000 × 123.1
    expect($payment->added_by)->toBe($this->user->id);

    expect($this->account->fresh()->balance)->toEqual('4507600.00');

    $entry = Transaction::where('source', 'lc_payment')->firstOrFail();
    expect($entry->type)->toBe('debit');
    expect($entry->debit)->toEqual('492400.00');
    expect($entry->usd_amount)->toEqual('4000.00');
    expect($entry->usd_rate)->toEqual('123.1000');
    expect($entry->payment_details)->toBe('ADV-001');
    expect($entry->created_at->toDateString())->toBe('2026-10-01');
    expect($entry->description)->toBe('LC payment LC0005 — $4,000.00 @ 123.1');

    // The dollars never leave: the account now holds them, at what they cost.
    $account = $this->account->fresh();
    expect($account->usd_balance)->toEqual('4000.00')
        ->and($account->usd_cost)->toEqual('492400.00')
        ->and($account->averageUsdRate())->toEqual(123.1);

    $dollars = DollarTransaction::sole();
    expect($dollars)->direction->toBe('in')->source->toBe('lc_payment')->reference->toBe('ADV-001')
        ->and($dollars->transactionable->is($payment))->toBeTrue()
        ->and($dollars->entry_date->toDateString())->toBe('2026-10-01');
});

test('an LC is paid in parts and cannot be paid more than is due', function () {
    $this->actingAs($this->user);

    payLc($this->lc, $this->account, ['usd_amount' => 6000])->assertSessionHas('success');
    payLc($this->lc, $this->account, ['usd_amount' => 4000.01])->assertSessionHas('error');
    payLc($this->lc, $this->account, ['usd_amount' => 4000, 'bank_rate' => 123.5])->assertSessionHas('success');

    $lc = $this->lc->fresh('payments');
    expect($lc->usdPaid())->toEqual(10000.0);
    expect($lc->usdDue())->toEqual(0.0);
    expect($lc->averageBankRate())->toEqual(123.26); // (6,000 × 123.1 + 4,000 × 123.5) / 10,000

    // Both parts are held, at the average of what they cost.
    $account = $this->account->fresh();
    expect($account->usd_balance)->toEqual('10000.00')
        ->and($account->usd_cost)->toEqual('1232600.00')
        ->and($account->averageUsdRate())->toEqual(123.26);
});

test('only an LC in dollars can be paid', function () {
    $this->lc->update(['currency' => 'EUR']);

    $this->actingAs($this->user);
    payLc($this->lc, $this->account)->assertSessionHas('error');

    expect(LcPayment::count())->toBe(0);
    expect($this->account->fresh()->balance)->toEqual('5000000.00');
});

test('once paid, the dollar bank charge is costed at the bank rate and the payment itself is not a cost', function () {
    $this->lc->update(['usd_sell_rate' => 120]);
    $this->order->recomputeFinancials();
    expect($this->order->fresh()->lc_cost)->toEqual('1200.00'); // $10 × the USD Sell Rate

    $this->actingAs($this->user);
    payLc($this->lc, $this->account)->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->lc_cost)->toEqual('1231.00'); // $10 × 123.1 — the $4,000 paid is not counted
});

test('reversing a payment returns its taka and takes back its dollars', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);
    $payment = LcPayment::firstOrFail();

    $this->delete(route('lc.payment.delete', [$this->lc->id, $payment->id]))->assertSessionHas('success');

    expect(LcPayment::count())->toBe(0);
    expect(Transaction::where('source', 'lc_payment')->count())->toBe(0);
    expect(DollarTransaction::count())->toBe(0);
    $account = $this->account->fresh();
    expect($account->balance)->toEqual('5000000.00')
        ->and($account->usd_balance)->toEqual('0.00')
        ->and($account->usd_cost)->toEqual('0.00');
});

test('a payment whose dollars were already spent cannot be reversed', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);
    $payment = LcPayment::firstOrFail();
    DB::transaction(fn () => DollarLedger::spend($this->account, 3500, 'order_cost'));

    $this->delete(route('lc.payment.delete', [$this->lc->id, $payment->id]))
        ->assertSessionHasErrors(['usd_amount' => 'Janata Bank holds $500.00 — $4,000.00 have already been spent.']);

    expect(LcPayment::count())->toBe(1);
    expect($this->account->fresh()->balance)->toEqual('4507600.00');
});

test('an LC with payments cannot be deleted until they are reversed', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->delete(route('lc.delete', $this->lc->id))->assertSessionHas('error');

    expect(Lc::find($this->lc->id))->not->toBeNull();
});

test('an LC charge paid in dollars comes out of the account\'s dollars at what they cost', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account); // $4,000 held at 123.1

    $this->post(route('lc.cost.store', $this->lc->id), [
        'title' => 'Swift charge',
        'currency' => 'USD',
        'usd_amount' => 25,
        'amount' => 1,
        'cost_date' => '2026-10-02',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHas('success');

    $cost = $this->lc->costs()->firstOrFail();
    expect($cost->amount)->toEqual('3077.50') // $25 × 123.1, whatever the form sent
        ->and($cost->usd_amount)->toEqual('25.00')
        ->and($cost->usd_rate)->toEqual('123.1000')
        ->and($cost->dollarNote())->toBe('$25.00 @ 123.1');

    // The dollars paid it, so no taka left the account for it.
    expect(Transaction::where('source', 'lc_cost')->count())->toBe(0);
    $account = $this->account->fresh();
    expect($account->balance)->toEqual('4507600.00')->and($account->usd_balance)->toEqual('3975.00');

    // Deleting the charge puts the dollars back.
    $this->delete(route('lc.cost.delete', [$this->lc->id, $cost->id]))->assertSessionHas('success');
    expect($this->account->fresh()->usd_balance)->toEqual('4000.00');
});

test('a dollar LC charge needs its dollars and the account they come from', function () {
    $this->actingAs($this->user);

    $this->post(route('lc.cost.store', $this->lc->id), [
        'title' => 'Swift charge',
        'currency' => 'USD',
    ])->assertSessionHasErrors(['usd_amount', 'payment_account_id']);

    // An account without enough dollars is refused.
    $this->post(route('lc.cost.store', $this->lc->id), [
        'title' => 'Swift charge', 'currency' => 'USD', 'usd_amount' => 25, 'payment_account_id' => $this->account->id,
    ])->assertSessionHasErrors(['usd_amount' => 'Janata Bank holds $0.00 — $25.00 are needed.']);
    expect($this->lc->costs()->count())->toBe(0);
});

test('LC payments never reach the P&L, not even as an exchange gain or loss', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account, ['usd_amount' => 4000]);

    $response = $this->get(route('reports.profit-loss'));
    expect($response->viewData('exchangeGainLoss'))->toEqual(0.0);
    expect($response->viewData('netProfit'))->toEqual(round($response->viewData('totals')['profit'] - $response->viewData('operating')['total'], 2));
});

test('the LC page lists the payments and where their dollars are kept', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->get(route('lc.show', $this->lc->id))
        ->assertOk()
        ->assertSee('LC Payments (1)')
        ->assertSee('taka buys the dollars, which stay in the account')
        ->assertSee('$6,000.00') // still due
        ->assertSee('Pay LC')
        ->assertDontSee('Gain / (Loss)');

    $this->get(route('order.show', $this->order->id))
        ->assertOk()
        ->assertDontSee('Profit after exchange');
});

test('the LC list flags dollar bank charges with no bank rate', function () {
    $this->actingAs($this->user);

    $this->get(route('lc.index'))->assertOk()->assertSee('1 dollar LC needs a USD Sell Rate.');

    $this->lc->update(['usd_sell_rate' => 121]);
    $this->get(route('lc.index'))->assertOk()->assertDontSee('needs a USD Sell Rate');
});

test('accounts show the dollars they hold and what they cost', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->get(route('payment.accounts'))->assertOk()->assertSee('$4,000.00')->assertSee('@ 123.1');
    $this->get(route('payment.account.book', $this->account->id))->assertOk()
        ->assertSee('Dollars:')
        ->assertSee('$4,000.00')
        ->assertSee('Bought with LC payment LC0005');
});

test('paying and reversing LC payments each need their own permission', function () {
    $clerk = userWithPermissions(['lc.view']);
    $this->actingAs($clerk);

    payLc($this->lc, $this->account)->assertForbidden();
    $this->get(route('lc.show', $this->lc->id))->assertOk()->assertDontSee('Pay LC');

    $payment = LcPayment::factory()->create(['lc_id' => $this->lc->id, 'payment_account_id' => $this->account->id]);
    $this->delete(route('lc.payment.delete', [$this->lc->id, $payment->id]))->assertForbidden();
});

test('the day rate lookup gives the rate in force on a date', function () {
    ExchangeRate::factory()->create(['rate_date' => '2026-10-01', 'usd_rate' => 122.5]);
    $this->actingAs($this->user);

    $this->getJson(route('exchange.rate.lookup', ['date' => '2026-10-01']))
        ->assertOk()->assertExactJson(['usd_rate' => 122.5, 'rate_date' => '2026-10-01', 'is_exact' => true]);

    $this->getJson(route('exchange.rate.lookup', ['date' => '2026-10-04']))
        ->assertOk()->assertJson(['rate_date' => '2026-10-01', 'is_exact' => false]);

    $this->getJson(route('exchange.rate.lookup', ['date' => '2026-09-01']))
        ->assertOk()->assertExactJson(['usd_rate' => null, 'rate_date' => null, 'is_exact' => false]);
});
