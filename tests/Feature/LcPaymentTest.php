<?php

use App\Models\Contact;
use App\Models\ExchangeRate;
use App\Models\Lc;
use App\Models\LcPayment;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'day_rate' => 122.5,
        'bank_rate' => 123.1,
        'reference' => 'ADV-001',
    ], $overrides));
}

test('paying a dollar LC takes the taka at the bank rate and records the exchange result', function () {
    $this->actingAs($this->user);

    payLc($this->lc, $this->account)->assertSessionHas('success');

    $payment = LcPayment::firstOrFail();
    expect($payment->bdt_amount)->toEqual('492400.00'); // $4,000 × 123.1
    expect($payment->exchange_gain_loss)->toEqual('-2400.00'); // $4,000 × (122.5 − 123.1)
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

    expect($this->account->usdSent())->toEqual(4000.0);
});

test('an LC is paid in parts and cannot be paid more than is due', function () {
    $this->actingAs($this->user);

    payLc($this->lc, $this->account, ['usd_amount' => 6000])->assertSessionHas('success');
    payLc($this->lc, $this->account, ['usd_amount' => 4000.01])->assertSessionHas('error');
    payLc($this->lc, $this->account, ['usd_amount' => 4000, 'day_rate' => 124, 'bank_rate' => 123.5])->assertSessionHas('success');

    $lc = $this->lc->fresh('payments');
    expect($lc->usdPaid())->toEqual(10000.0);
    expect($lc->usdDue())->toEqual(0.0);
    // −3,600 on the first part, +2,000 on the second.
    expect($lc->exchangeGainLoss())->toEqual(-1600.0);
    expect($lc->averageBankRate())->toEqual(123.26); // (6,000 × 123.1 + 4,000 × 123.5) / 10,000
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

test('reversing a payment returns its taka and removes its ledger entry', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);
    $payment = LcPayment::firstOrFail();

    $this->delete(route('lc.payment.delete', [$this->lc->id, $payment->id]))->assertSessionHas('success');

    expect(LcPayment::count())->toBe(0);
    expect(Transaction::where('source', 'lc_payment')->count())->toBe(0);
    expect($this->account->fresh()->balance)->toEqual('5000000.00');
    expect($this->account->usdSent())->toEqual(0.0);
});

test('an LC with payments cannot be deleted until they are reversed', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->delete(route('lc.delete', $this->lc->id))->assertSessionHas('error');

    expect(Lc::find($this->lc->id))->not->toBeNull();
});

test('an LC charge paid in dollars is booked at its taka value and keeps the dollars', function () {
    $this->actingAs($this->user);

    $this->post(route('lc.cost.store', $this->lc->id), [
        'title' => 'Swift charge',
        'currency' => 'USD',
        'usd_amount' => 25,
        'usd_rate' => 122.4,
        'amount' => 1,
        'cost_date' => '2026-10-02',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHas('success');

    $cost = $this->lc->costs()->firstOrFail();
    expect($cost->amount)->toEqual('3060.00'); // $25 × 122.4, whatever the form sent
    expect($cost->usd_amount)->toEqual('25.00');
    expect($cost->usd_rate)->toEqual('122.4000');

    $entry = Transaction::where('source', 'lc_cost')->firstOrFail();
    expect($entry->debit)->toEqual('3060.00');
    expect($entry->usd_amount)->toEqual('25.00');
    expect($this->account->fresh()->balance)->toEqual('4996940.00');
});

test('a dollar LC charge needs its dollars and rate', function () {
    $this->actingAs($this->user);

    $this->post(route('lc.cost.store', $this->lc->id), [
        'title' => 'Swift charge',
        'currency' => 'USD',
    ])->assertSessionHasErrors(['usd_amount', 'usd_rate']);
});

test('the P&L counts the exchange result of LC payments on the day paid, never the payments', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account, ['paid_on' => '2026-10-01', 'usd_amount' => 4000]); // −2,400
    payLc($this->lc, $this->account, ['paid_on' => '2026-11-15', 'usd_amount' => 1000, 'day_rate' => 124, 'bank_rate' => 123]); // +1,000

    $all = $this->get(route('reports.profit-loss'));
    expect($all->viewData('exchangeGainLoss'))->toEqual(-1400.0);
    $gross = $all->viewData('totals')['profit'];
    expect($all->viewData('netProfit'))->toEqual(round($gross - $all->viewData('operating')['total'] - 1400, 2));

    $october = $this->get(route('reports.profit-loss', ['from' => '2026-10-01', 'to' => '2026-10-31']));
    expect($october->viewData('exchangeGainLoss'))->toEqual(-2400.0);
});

test('the P&L exchange line follows the customer filter', function () {
    $customer = Contact::findOrFail($this->order->customer_id);
    $otherOrder = Order::factory()->create();
    $otherLc = Lc::factory()->create(['order_id' => $otherOrder->id, 'invoice_amount' => 5000, 'currency' => 'USD']);

    $this->actingAs($this->user);
    payLc($this->lc, $this->account); // −2,400
    payLc($otherLc, $this->account, ['usd_amount' => 1000, 'day_rate' => 124, 'bank_rate' => 123]); // +1,000

    $response = $this->get(route('reports.profit-loss', ['customer_id' => $customer->id]));
    expect($response->viewData('exchangeGainLoss'))->toEqual(-2400.0);
});

test('the LC page shows the payments and the order page the exchange result', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->get(route('lc.show', $this->lc->id))
        ->assertOk()
        ->assertSee('LC Payments (1)')
        ->assertSee('$6,000.00') // still due
        ->assertSee('Pay LC')
        ->assertSee('(৳ 2,400.00)');

    $this->get(route('order.show', $this->order->id))
        ->assertOk()
        ->assertSee('Exchange gain / (loss) on LC payments')
        ->assertSee('Profit after exchange');
});

test('the LC list flags dollar bank charges with no bank rate', function () {
    $this->actingAs($this->user);

    $this->get(route('lc.index'))->assertOk()->assertSee('1 dollar LC needs a USD Sell Rate.');

    $this->lc->update(['usd_sell_rate' => 121]);
    $this->get(route('lc.index'))->assertOk()->assertDontSee('needs a USD Sell Rate');
});

test('accounts show the dollars sent through them', function () {
    $this->actingAs($this->user);
    payLc($this->lc, $this->account);

    $this->get(route('payment.accounts'))->assertOk()->assertSee('$4,000.00');
    $this->get(route('payment.account.book', $this->account->id))->assertOk()->assertSee('Dollars Sent:')->assertSee('$4,000.00 @ 123.1');
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
