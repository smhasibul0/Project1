<?php

use App\Models\PaymentAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('deposit increases the account balance and records a credit transaction', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);

    $response = $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $account->id,
        'amount' => 250,
        'deposit_from' => 'cash',
        'deposit_date' => '2026-07-03',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($account->fresh()->balance)->toEqual('350.00');

    $txn = Transaction::where('payment_account_id', $account->id)->first();
    expect($txn)->not->toBeNull();
    expect($txn->type)->toBe('credit');
    expect($txn->credit)->toEqual('250.00');
    expect($txn->running_balance)->toEqual('350.00');
    expect($txn->added_by)->toBe($this->user->id);
});

test('fund transfer moves balance and records debit and credit ledger rows', function () {
    $from = PaymentAccount::factory()->create(['balance' => 500]);
    $to = PaymentAccount::factory()->create(['balance' => 0]);

    $response = $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 200,
        'transfer_date' => '2026-07-03',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($from->fresh()->balance)->toEqual('300.00');
    expect($to->fresh()->balance)->toEqual('200.00');

    $debit = Transaction::where('payment_account_id', $from->id)->first();
    expect($debit->type)->toBe('debit');
    expect($debit->debit)->toEqual('200.00');
    expect($debit->running_balance)->toEqual('300.00');

    $credit = Transaction::where('payment_account_id', $to->id)->first();
    expect($credit->type)->toBe('credit');
    expect($credit->credit)->toEqual('200.00');
    expect($credit->running_balance)->toEqual('200.00');
});

test('fund transfer to the same account is rejected', function () {
    $account = PaymentAccount::factory()->create(['balance' => 500]);

    $response = $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => 100,
        'transfer_date' => '2026-07-03',
    ]);

    $response->assertSessionHasErrors('to_account_id');
    expect($account->fresh()->balance)->toEqual('500.00');
    expect(Transaction::count())->toBe(0);
});

test('the account book page renders with its transactions', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);
    $account->transactions()->create([
        'type' => 'credit',
        'amount' => 100,
        'credit' => 100,
        'running_balance' => 100,
        'description' => 'Deposit',
        'added_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('payment.account.book', $account->id));

    $response->assertOk();
    $response->assertSee('Account Book');
    $response->assertSee('Deposit');
});

test('deleting a deposit reverses the balance and removes the row', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);

    $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $account->id,
        'amount' => 250,
        'deposit_date' => '2026-07-04',
    ]);

    $txn = Transaction::where('payment_account_id', $account->id)->firstOrFail();

    $response = $this->actingAs($this->user)->delete(route('transaction.delete', $txn->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($account->fresh()->balance)->toEqual('100.00');
    expect(Transaction::count())->toBe(0);
});

test('deleting one leg of a fund transfer reverses both accounts and removes both legs', function () {
    $from = PaymentAccount::factory()->create(['balance' => 500]);
    $to = PaymentAccount::factory()->create(['balance' => 0]);

    $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 200,
        'transfer_date' => '2026-07-04',
    ]);

    $leg = Transaction::where('payment_account_id', $from->id)->firstOrFail();

    $this->actingAs($this->user)->delete(route('transaction.delete', $leg->id));

    expect($from->fresh()->balance)->toEqual('500.00');
    expect($to->fresh()->balance)->toEqual('0.00');
    expect(Transaction::count())->toBe(0);
});

test('editing a deposit adjusts the balance and running balance', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);

    $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $account->id,
        'amount' => 250,
        'deposit_date' => '2026-07-04',
    ]);

    $txn = Transaction::where('payment_account_id', $account->id)->firstOrFail();

    $response = $this->actingAs($this->user)->put(route('transaction.update', $txn->id), [
        'amount' => 400,
        'date' => '2026-07-04',
        'note' => 'corrected amount',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($account->fresh()->balance)->toEqual('500.00');

    $txn->refresh();
    expect($txn->credit)->toEqual('400.00');
    expect($txn->running_balance)->toEqual('500.00');
    expect($txn->note)->toBe('corrected amount');
});

test('editing a fund transfer amount adjusts both accounts', function () {
    $from = PaymentAccount::factory()->create(['balance' => 500]);
    $to = PaymentAccount::factory()->create(['balance' => 0]);

    $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 200,
        'transfer_date' => '2026-07-04',
    ]);

    $leg = Transaction::where('payment_account_id', $from->id)->firstOrFail();

    $this->actingAs($this->user)->put(route('transaction.update', $leg->id), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 350,
        'date' => '2026-07-04',
    ]);

    expect($from->fresh()->balance)->toEqual('150.00');
    expect($to->fresh()->balance)->toEqual('350.00');
});

test('a fund transfer can be re-pointed to different accounts', function () {
    $a = PaymentAccount::factory()->create(['balance' => 500]);
    $b = PaymentAccount::factory()->create(['balance' => 0]);
    $c = PaymentAccount::factory()->create(['balance' => 500]);

    // Transfer a -> b of 200.
    $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 200,
        'transfer_date' => '2026-07-04',
    ]);

    $debitLeg = Transaction::where('payment_account_id', $a->id)->firstOrFail();

    // Re-point the source from a to c (still 200 into b).
    $this->actingAs($this->user)->put(route('transaction.update', $debitLeg->id), [
        'from_account_id' => $c->id,
        'to_account_id' => $b->id,
        'amount' => 200,
        'date' => '2026-07-04',
    ]);

    // a is refunded, c is now the source, b unchanged.
    expect($a->fresh()->balance)->toEqual('500.00');
    expect($b->fresh()->balance)->toEqual('200.00');
    expect($c->fresh()->balance)->toEqual('300.00');

    // The debit leg now belongs to c, and a has no transactions left.
    expect(Transaction::where('payment_account_id', $c->id)->where('type', 'debit')->count())->toBe(1);
    expect(Transaction::where('payment_account_id', $a->id)->count())->toBe(0);
});

test('running balances are recomputed when an earlier transaction is deleted', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);

    $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $account->id,
        'amount' => 50,
        'deposit_date' => '2026-01-01',
    ]);
    $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $account->id,
        'amount' => 30,
        'deposit_date' => '2026-01-02',
    ]);

    $first = Transaction::where('payment_account_id', $account->id)->orderBy('created_at')->firstOrFail();
    $this->actingAs($this->user)->delete(route('transaction.delete', $first->id));

    expect($account->fresh()->balance)->toEqual('130.00');

    $second = Transaction::where('payment_account_id', $account->id)->firstOrFail();
    expect($second->running_balance)->toEqual('130.00');
});

test('a fund transfer is blocked when the source has insufficient balance', function () {
    $from = PaymentAccount::factory()->create(['balance' => 100]);
    $to = PaymentAccount::factory()->create(['balance' => 0]);

    $response = $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 250,
        'transfer_date' => '2026-07-04',
    ]);

    $response->assertSessionHasErrors('amount');
    expect($from->fresh()->balance)->toEqual('100.00');
    expect($to->fresh()->balance)->toEqual('0.00');
    expect(Transaction::count())->toBe(0);
});

test('editing a fund transfer is blocked when the source cannot cover the new amount', function () {
    $from = PaymentAccount::factory()->create(['balance' => 500]);
    $to = PaymentAccount::factory()->create(['balance' => 0]);

    $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 200,
        'transfer_date' => '2026-07-04',
    ]);

    $leg = Transaction::where('payment_account_id', $from->id)->firstOrFail();

    $response = $this->actingAs($this->user)->put(route('transaction.update', $leg->id), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 600,
        'date' => '2026-07-04',
    ]);

    $response->assertSessionHasErrors('amount');
    expect($from->fresh()->balance)->toEqual('300.00');
    expect($to->fresh()->balance)->toEqual('200.00');
});

test('deleting a deposit is blocked when it would overdraw the account', function () {
    $a = PaymentAccount::factory()->create(['balance' => 0]);
    $b = PaymentAccount::factory()->create(['balance' => 0]);

    $this->actingAs($this->user)->post(route('payment.account.deposit'), [
        'account_id' => $a->id,
        'amount' => 100,
        'deposit_date' => '2026-07-04',
    ]);
    $this->actingAs($this->user)->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $a->id,
        'to_account_id' => $b->id,
        'amount' => 100,
        'transfer_date' => '2026-07-04',
    ]);

    $deposit = Transaction::where('payment_account_id', $a->id)->where('source', 'deposit')->firstOrFail();

    $response = $this->actingAs($this->user)->delete(route('transaction.delete', $deposit->id));

    $response->assertSessionHasErrors('amount');
    expect($a->fresh()->balance)->toEqual('0.00');
    expect(Transaction::find($deposit->id))->not->toBeNull();
});

test('a system transaction cannot be edited or deleted from the account book', function () {
    $account = PaymentAccount::factory()->create(['balance' => 100]);
    $txn = $account->transactions()->create([
        'type' => 'debit',
        'source' => null,
        'amount' => 40,
        'debit' => 40,
        'running_balance' => 60,
        'description' => 'Purchase payment',
    ]);

    $this->actingAs($this->user)->delete(route('transaction.delete', $txn->id))
        ->assertSessionHas('error');

    $this->actingAs($this->user)->put(route('transaction.update', $txn->id), [
        'amount' => 999,
        'date' => '2026-07-04',
    ])->assertSessionHas('error');

    expect($account->fresh()->balance)->toEqual('100.00');
    expect(Transaction::find($txn->id))->not->toBeNull();
});
