<?php

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = adminUser();
    $this->account = PaymentAccount::factory()->create(['balance' => 100000]);
});

/**
 * Record a loan straight to the database, bypassing the HTTP layer.
 */
function loan(string $direction, array $overrides = []): Loan
{
    return Loan::create(array_merge([
        'loan_code' => Loan::nextCode($direction),
        'direction' => $direction,
        'counterparty' => 'City Bank',
        'counterparty_type' => 'bank',
        'principal' => 100000,
        'interest_type' => 'none',
        'start_date' => '2026-01-01',
        'status' => 'active',
    ], $overrides));
}

test('both loan pages render with their totals', function () {
    $borrowing = loan('borrowed', ['principal' => 50000, 'interest_type' => 'fixed', 'interest_amount' => 5000]);
    $borrowing->payments()->create(['amount' => 20000, 'paid_on' => '2026-03-01']);
    loan('lent', ['principal' => 30000, 'counterparty' => 'Delta Traders', 'counterparty_type' => 'company']);

    $response = $this->actingAs($this->user)->get(route('loans.index', 'borrowed'));
    $response->assertOk();
    $response->assertViewHas('principalTotal', 50000.0);
    $response->assertViewHas('interestTotal', 5000.0);
    $response->assertViewHas('paidTotal', 20000.0);
    $response->assertViewHas('outstandingTotal', 35000.0);
    // The other direction keeps to its own page.
    $response->assertDontSee('Delta Traders');

    $this->actingAs($this->user)->get(route('loans.index', 'lent'))
        ->assertOk()
        ->assertViewHas('outstandingTotal', 30000.0)
        ->assertSee('Delta Traders');
});

test('an unknown direction is not a page', function () {
    $this->actingAs($this->user)->get('/finance/sideways')->assertNotFound();
});
test('loan codes run in sequence per direction', function () {
    expect(Loan::nextCode('borrowed'))->toBe('BRW-0001');
    expect(Loan::nextCode('lent'))->toBe('LND-0001');

    loan('borrowed');
    expect(Loan::nextCode('borrowed'))->toBe('BRW-0002');
    // Lending keeps its own run of numbers.
    expect(Loan::nextCode('lent'))->toBe('LND-0001');
});

test('a loan with no interest is only ever the principal', function () {
    $borrowing = loan('borrowed');

    expect($borrowing->interestToDate())->toEqual(0.0);
    expect($borrowing->totalPayable())->toEqual(100000.0);
});

test('a fixed interest charge is owed in full from day one', function () {
    $borrowing = loan('borrowed', ['interest_type' => 'fixed', 'interest_amount' => 5000]);

    expect($borrowing->interestToDate(Carbon::parse('2026-01-02')))->toEqual(5000.0);
    expect($borrowing->totalPayable(Carbon::parse('2026-01-02')))->toEqual(105000.0);
});

test('percentage interest accrues over the days the money is out', function () {
    $borrowing = loan('borrowed', ['interest_type' => 'percentage', 'interest_rate' => 12]);

    // A full year at 12% on 100,000.
    expect($borrowing->interestToDate(Carbon::parse('2027-01-01')))->toEqual(12000.0);
    // Half a year is roughly half the interest.
    expect(round($borrowing->interestToDate(Carbon::parse('2026-07-01')), 0))->toEqual(5951.0);
    // Nothing has accrued on day one.
    expect($borrowing->interestToDate(Carbon::parse('2026-01-01')))->toEqual(0.0);
});

test('percentage interest stops growing at the due date', function () {
    $borrowing = loan('borrowed', [
        'interest_type' => 'percentage',
        'interest_rate' => 12,
        'due_date' => '2026-07-01',
    ]);

    $atDue = $borrowing->interestToDate(Carbon::parse('2026-07-01'));

    // Long past the due date, the figure is the same as it was on the day.
    expect($borrowing->interestToDate(Carbon::parse('2028-01-01')))->toEqual($atDue);
});

test('borrowing money puts the principal into the account', function () {
    $this->actingAs($this->user)->post(route('loan.store', 'borrowed'), [
        'counterparty' => 'City Bank',
        'counterparty_type' => 'bank',
        'principal' => 50000,
        'interest_type' => 'percentage',
        'interest_rate' => 10,
        'start_date' => '2026-02-01',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('150000.00');

    $transaction = Transaction::where('source', 'loan_received')->firstOrFail();
    expect($transaction->credit)->toEqual('50000.00');
    expect($transaction->reference)->toBe('BRW-0001');
});

test('lending money takes the principal out of the account', function () {
    $this->actingAs($this->user)->post(route('loan.store', 'lent'), [
        'counterparty' => 'Mr Rahman',
        'counterparty_type' => 'individual',
        'principal' => 40000,
        'interest_type' => 'none',
        'start_date' => '2026-02-01',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('60000.00');

    $transaction = Transaction::where('source', 'loan_given')->firstOrFail();
    expect($transaction->debit)->toEqual('40000.00');
});

test('a repayment on a borrowing comes off the account', function () {
    $borrowing = loan('borrowed', ['principal' => 50000]);

    $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
        'amount' => 20000,
        'paid_on' => '2026-03-01',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('80000.00');
    $borrowing = $borrowing->fresh('payments');
    expect($borrowing->paidTotal())->toEqual(20000.0);
    expect($borrowing->outstanding())->toEqual(30000.0);
    expect($borrowing->paymentStatus())->toBe('partial');
});

test('a receipt on a lending goes onto the account', function () {
    $lending = loan('lent', ['principal' => 50000, 'counterparty' => 'Mr Rahman']);

    $this->actingAs($this->user)->post(route('loan.payments.store', $lending->id), [
        'amount' => 20000,
        'paid_on' => '2026-03-01',
        'payment_account_id' => $this->account->id,
    ])->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('120000.00');
    expect(Transaction::where('source', 'loan_receipt')->firstOrFail()->credit)->toEqual('20000.00');
});

test('paying the balance settles the loan on its own', function () {
    $borrowing = loan('borrowed', ['principal' => 50000, 'interest_type' => 'fixed', 'interest_amount' => 5000]);

    foreach ([30000, 25000] as $amount) {
        $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
            'amount' => $amount,
            'paid_on' => '2026-03-01',
        ])->assertSessionHasNoErrors();
    }

    $borrowing = $borrowing->fresh('payments');
    expect($borrowing->outstanding())->toEqual(0.0);
    expect($borrowing->paymentStatus())->toBe('settled');
    expect($borrowing->status)->toBe('settled');
});

test('a payment larger than the outstanding balance is rejected', function () {
    $borrowing = loan('borrowed', ['principal' => 50000]);

    $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
        'amount' => 50001,
        'paid_on' => '2026-03-01',
    ])->assertSessionHasErrors('amount');

    expect($borrowing->fresh('payments')->payments)->toHaveCount(0);
});

test('interest counts towards what is owed', function () {
    $borrowing = loan('borrowed', ['principal' => 50000, 'interest_type' => 'fixed', 'interest_amount' => 5000]);

    expect($borrowing->totalPayable())->toEqual(55000.0);
    expect($borrowing->outstanding())->toEqual(55000.0);

    // Repaying the principal alone still leaves the interest owed.
    $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
        'amount' => 50000,
        'paid_on' => '2026-03-01',
    ]);

    expect($borrowing->fresh('payments')->outstanding())->toEqual(5000.0);
});

test('deleting a payment puts the account back and reopens the loan', function () {
    $borrowing = loan('borrowed', ['principal' => 50000]);

    $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
        'amount' => 50000,
        'paid_on' => '2026-03-01',
        'payment_account_id' => $this->account->id,
    ]);
    expect($this->account->fresh()->balance)->toEqual('50000.00');
    expect($borrowing->fresh()->status)->toBe('settled');

    $payment = $borrowing->fresh('payments')->payments->first();
    $this->actingAs($this->user)->delete(route('loan.payments.delete', [$borrowing->id, $payment->id]))
        ->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('100000.00');
    expect($borrowing->fresh()->status)->toBe('active');
    expect(Transaction::where('transactionable_type', LoanPayment::class)->count())->toBe(0);
});

test('deleting a loan reverses its principal and every payment', function () {
    $this->actingAs($this->user)->post(route('loan.store', 'borrowed'), [
        'counterparty' => 'City Bank',
        'counterparty_type' => 'bank',
        'principal' => 50000,
        'interest_type' => 'none',
        'start_date' => '2026-02-01',
        'payment_account_id' => $this->account->id,
    ]);
    $borrowing = Loan::firstOrFail();

    $this->actingAs($this->user)->post(route('loan.payments.store', $borrowing->id), [
        'amount' => 20000,
        'paid_on' => '2026-03-01',
        'payment_account_id' => $this->account->id,
    ]);
    // 100,000 + 50,000 borrowed - 20,000 repaid.
    expect($this->account->fresh()->balance)->toEqual('130000.00');

    $this->actingAs($this->user)->delete(route('loan.delete', $borrowing->id))->assertSessionHasNoErrors();

    expect($this->account->fresh()->balance)->toEqual('100000.00');
    expect(Loan::count())->toBe(0);
    expect(LoanPayment::count())->toBe(0);
    expect(Transaction::whereIn('source', ['loan_received', 'loan_repayment'])->count())->toBe(0);
});

test('a rate is required for percentage interest and an amount for a fixed charge', function () {
    $base = [
        'counterparty' => 'City Bank',
        'counterparty_type' => 'bank',
        'principal' => 50000,
        'start_date' => '2026-02-01',
    ];

    $this->actingAs($this->user)
        ->post(route('loan.store', 'borrowed'), $base + ['interest_type' => 'percentage'])
        ->assertSessionHasErrors('interest_rate');

    $this->actingAs($this->user)
        ->post(route('loan.store', 'borrowed'), $base + ['interest_type' => 'fixed'])
        ->assertSessionHasErrors('interest_amount');

    expect(Loan::count())->toBe(0);
});

test('the interest basis not chosen is never stored', function () {
    $this->actingAs($this->user)->post(route('loan.store', 'borrowed'), [
        'counterparty' => 'City Bank',
        'counterparty_type' => 'bank',
        'principal' => 50000,
        'interest_type' => 'percentage',
        'interest_rate' => 12,
        'interest_amount' => 9999,
        'start_date' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $borrowing = Loan::firstOrFail();
    expect($borrowing->interest_rate)->toEqual('12.00');
    expect($borrowing->interest_amount)->toBeNull();
});
