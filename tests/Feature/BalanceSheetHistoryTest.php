<?php

use App\Models\CompanySetting;
use App\Models\Loan;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Support\DollarLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Midday on 13 Oct 2026 in Dhaka.
    Carbon::setTestNow(Carbon::parse('2026-10-13 12:00', 'Asia/Dhaka'));
    $this->user = adminUser();
    $this->bank = PaymentAccount::factory()->create(['name' => 'Janata Bank', 'balance' => 1000, 'created_at' => '2026-09-01 04:00:00']);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * The balance sheet's figures for a day.
 */
function sheetOn(?string $day = null): TestResponse
{
    return test()->get(route('reports.balance-sheet', $day ? ['date' => $day] : []));
}

test('an account holds on an earlier day what it held before the money that moved later', function () {
    $this->actingAs($this->user);
    $this->post(route('payment.account.deposit'), ['account_id' => $this->bank->id, 'amount' => 500, 'deposit_date' => '2026-10-10']);

    expect(sheetOn()->viewData('cashBank'))->toEqual(1500.0);
    expect(sheetOn('2026-10-10')->viewData('cashBank'))->toEqual(1500.0);
    expect(sheetOn('2026-10-09')->viewData('cashBank'))->toEqual(1000.0);
});

test('an account opened after the day is not on that day\'s sheet', function () {
    PaymentAccount::factory()->create(['name' => 'City Bank', 'balance' => 700, 'created_at' => '2026-10-11 04:00:00']);
    $this->actingAs($this->user);

    $earlier = sheetOn('2026-10-10');
    expect($earlier->viewData('accounts')->pluck('name')->all())->toBe(['Janata Bank']);
    expect($earlier->viewData('cashBank'))->toEqual(1000.0);
    expect(sheetOn()->viewData('cashBank'))->toEqual(1700.0);
});

test('dollars held count on the days they were held, at what they cost', function () {
    DB::transaction(fn () => DollarLedger::receive($this->bank, 100, 122, 'deposit', ['entry_date' => '2026-10-05']));
    DB::transaction(fn () => DollarLedger::spend($this->bank, 40, 'order_cost', ['entry_date' => '2026-10-08']));
    $this->actingAs($this->user);

    expect(sheetOn('2026-10-04')->viewData('dollarsHeld'))->toEqual(0.0);
    expect(sheetOn('2026-10-05'))->viewData('dollarsHeld')->toEqual(100.0)->viewData('dollarsAtCost')->toEqual(12200.0)->viewData('cashBank')->toEqual(13200.0);
    expect(sheetOn('2026-10-08'))->viewData('dollarsHeld')->toEqual(60.0)->viewData('dollarsAtCost')->toEqual(7320.0);
});

test('receivables are the orders placed by the day less what had been paid by then', function () {
    $order = Order::factory()->create(['order_date' => '2026-10-01', 'total_amount' => 1000, 'due_amount' => 1000]);
    $order->payments()->create(['amount' => 400, 'payment_date' => '2026-10-08']);
    Order::factory()->create(['order_date' => '2026-10-10', 'total_amount' => 300, 'due_amount' => 300]);
    $this->actingAs($this->user);

    expect(sheetOn('2026-09-30')->viewData('receivables'))->toEqual(0.0);
    expect(sheetOn('2026-10-05')->viewData('receivables'))->toEqual(1000.0);
    expect(sheetOn('2026-10-08')->viewData('receivables'))->toEqual(600.0);
    expect(sheetOn()->viewData('receivables'))->toEqual(900.0);
});

test('a loan counts from the day it started until the day it was settled', function () {
    $this->actingAs($this->user);
    $this->post(route('loan.store', 'borrowed'), [
        'counterparty' => 'City Bank', 'counterparty_type' => 'bank', 'principal' => 5000,
        'interest_type' => 'none', 'start_date' => '2026-10-02',
    ]);
    $loan = Loan::sole();
    $this->post(route('loan.payments.store', $loan->id), ['amount' => 2000, 'paid_on' => '2026-10-06']);
    $this->post(route('loan.payments.store', $loan->id), ['amount' => 3000, 'paid_on' => '2026-10-09']);

    expect($loan->fresh())->status->toBe('settled')->closed_on->toDateString()->toBe('2026-10-09');

    expect(sheetOn('2026-10-01')->viewData('loansPayable'))->toEqual(0.0);
    expect(sheetOn('2026-10-05')->viewData('loansPayable'))->toEqual(5000.0);
    expect(sheetOn('2026-10-07')->viewData('loansPayable'))->toEqual(3000.0);
    expect(sheetOn('2026-10-09')->viewData('borrowings'))->toHaveCount(0);
    expect(sheetOn()->viewData('loansPayable'))->toEqual(0.0);
});

test('closing a loan by hand keeps the day, and reopening it clears it', function () {
    $loan = Loan::create([
        'loan_code' => 'LND-0001', 'direction' => 'lent', 'counterparty' => 'Mr Rahman', 'counterparty_type' => 'individual',
        'principal' => 4000, 'interest_type' => 'none', 'start_date' => '2026-10-01', 'status' => 'active',
    ]);
    $this->actingAs($this->user);

    $this->post(route('loan.close', $loan->id), ['status' => 'written_off'])->assertSessionHas('success');
    expect($loan->fresh()->closed_on->toDateString())->toBe('2026-10-13');
    expect(sheetOn('2026-10-12')->viewData('loansReceivable'))->toEqual(4000.0);
    expect(sheetOn()->viewData('loansReceivable'))->toEqual(0.0);

    $this->post(route('loan.close', $loan->id), ['status' => 'active']);
    expect($loan->fresh()->closed_on)->toBeNull();
});

test('the sheet opens on today, and a day still to come or not a date shows today', function () {
    $this->actingAs($this->user);

    sheetOn()->assertOk()->assertSee('13 Oct 2026')->assertSee('(today, so far)')->assertSee('Download PDF');
    expect(sheetOn('2026-12-31')->viewData('date')->toDateString())->toBe('2026-10-13');
    expect(sheetOn('not-a-date')->viewData('date')->toDateString())->toBe('2026-10-13');

    sheetOn('2026-10-05')->assertOk()
        ->assertSee('05 Oct 2026')
        ->assertDontSee('(today, so far)')
        ->assertSee(route('reports.balance-sheet.pdf', ['date' => '2026-10-05']));
});

test('the sheet for a day downloads as a PDF', function () {
    CompanySetting::current()->update(['company_name' => 'Redwan Trading Corporation']);
    $this->user->update(['first_name' => 'Hasan', 'last_name' => 'Ali']);
    $this->actingAs($this->user);

    $response = $this->get(route('reports.balance-sheet.pdf', ['date' => '2026-10-05']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="Balance Sheet 2026-10-05.pdf"');

    $text = (new Parser)->parseContent($response->getContent())->getText();
    expect($text)->toContain('BALANCE SHEET')
        ->toContain('05 Oct 2026')
        ->toContain('Redwan Trading Corporation')
        ->toContain('Janata Bank')
        ->toContain('৳')
        ->toContain('by Hasan Ali')
        ->not->toContain('?');
});

test('the dated sheet and its PDF need the balance sheet permission', function () {
    $this->actingAs(userWithPermissions(['reports.profit-loss']));

    sheetOn('2026-10-05')->assertForbidden();
    $this->get(route('reports.balance-sheet.pdf'))->assertForbidden();
});
