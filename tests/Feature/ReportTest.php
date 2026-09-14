<?php

use App\Models\AccountType;
use App\Models\Loan;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('profit & loss aggregates order financials including freight and duty', function () {
    Order::factory()->create([
        'order_date' => '2026-07-01', 'total_amount' => 1000, 'profit' => 300,
        'freight_cost' => 400, 'duty_total' => 100,
        'total_expense' => 100, 'lc_cost' => 50, 'container_cost' => 50,
    ]);
    Order::factory()->create([
        'order_date' => '2026-07-02', 'total_amount' => 2000, 'profit' => 500,
        'freight_cost' => 1200, 'duty_total' => 300,
        'total_expense' => 0, 'lc_cost' => 0, 'container_cost' => 0,
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.profit-loss'));

    $response->assertOk();
    // revenue 3000, gross profit 800, freight 1600, duty 400
    $response->assertSee('3,000.00');
    $response->assertSee('800.00');
    $response->assertSee('1,600.00');
    $response->assertSee('400.00');
    // No operating expenses → Net Profit equals gross order profit.
    expect($response->viewData('netProfit'))->toBe(800.0);
});

test('profit & loss respects the date filter', function () {
    Order::factory()->create(['order_date' => '2026-07-01', 'total_amount' => 1000, 'profit' => 300]);
    Order::factory()->create(['order_date' => '2026-01-01', 'total_amount' => 9999, 'profit' => 111]);

    $response = $this->actingAs($this->user)->get(route('reports.profit-loss', ['from' => '2026-06-01', 'to' => '2026-07-31']));

    $response->assertOk();
    $response->assertSee('1,000.00');
    $response->assertDontSee('9,999.00');
});

test('receivables lists only orders with a balance due', function () {
    Order::factory()->create(['order_date' => now()->toDateString(), 'total_amount' => 1000, 'received_amount' => 400, 'due_amount' => 600, 'payment_status' => 'partial']);
    Order::factory()->create(['order_date' => now()->toDateString(), 'total_amount' => 500, 'received_amount' => 500, 'due_amount' => 0, 'payment_status' => 'paid']);

    $response = $this->actingAs($this->user)->get(route('reports.receivables'));

    $response->assertOk();
    $response->assertViewHas('totalDue', 600.0);
    expect($response->viewData('rows'))->toHaveCount(1);
});

test('balance sheet sums account balances and receivables', function () {
    $type = AccountType::create(['name' => 'Bank']);
    PaymentAccount::create(['name' => 'Bank A', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);
    PaymentAccount::create(['name' => 'Bank B', 'account_type_id' => $type->id, 'balance' => 500, 'is_active' => true]);
    Order::factory()->create(['due_amount' => 300]);

    $response = $this->actingAs($this->user)->get(route('reports.balance-sheet'));

    $response->assertOk();
    $response->assertViewHas('cashBank', 1500.0);
    $response->assertViewHas('receivables', 300.0);
    $response->assertViewHas('total', 1800.0);
});

test('balance sheet counts money lent as an asset and money borrowed as a liability', function () {
    $type = AccountType::create(['name' => 'Bank']);
    PaymentAccount::create(['name' => 'Bank A', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);

    $lending = Loan::create([
        'loan_code' => 'LND-0001', 'direction' => 'lent', 'counterparty' => 'Mr Rahman',
        'counterparty_type' => 'individual', 'principal' => 800, 'interest_type' => 'none',
        'start_date' => '2026-01-01', 'status' => 'active',
    ]);
    // Part of it has already come back, so only the remainder is still an asset.
    $lending->payments()->create(['amount' => 300, 'paid_on' => '2026-02-01']);

    Loan::create([
        'loan_code' => 'BRW-0001', 'direction' => 'borrowed', 'counterparty' => 'City Bank',
        'counterparty_type' => 'bank', 'principal' => 2000, 'interest_type' => 'fixed',
        'interest_amount' => 100, 'start_date' => '2026-01-01', 'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.balance-sheet'));

    $response->assertOk();
    $response->assertViewHas('loansReceivable', 500.0);
    $response->assertViewHas('loansPayable', 2100.0);
    // 1,000 cash + 500 still owed to us.
    $response->assertViewHas('total', 1500.0);
    $response->assertViewHas('netWorth', -600.0);
});

test('balance sheet ignores loans that are settled or written off', function () {
    $type = AccountType::create(['name' => 'Bank']);
    PaymentAccount::create(['name' => 'Bank A', 'account_type_id' => $type->id, 'balance' => 1000, 'is_active' => true]);

    foreach (['settled', 'written_off'] as $status) {
        Loan::create([
            'loan_code' => 'BRW-'.$status, 'direction' => 'borrowed', 'counterparty' => 'City Bank',
            'counterparty_type' => 'bank', 'principal' => 5000, 'interest_type' => 'none',
            'start_date' => '2026-01-01', 'status' => $status,
        ]);
    }

    $response = $this->actingAs($this->user)->get(route('reports.balance-sheet'));

    $response->assertOk();
    $response->assertViewHas('loansPayable', 0.0);
    $response->assertViewHas('netWorth', 1000.0);
});
test('cash flow totals money in and out from the ledger', function () {
    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash', 'account_type_id' => $type->id, 'balance' => 0, 'is_active' => true]);

    Transaction::create(['payment_account_id' => $account->id, 'type' => 'credit', 'source' => 'order_payment', 'amount' => 500, 'credit' => 500, 'debit' => 0, 'running_balance' => 500, 'created_at' => '2026-07-01']);
    Transaction::create(['payment_account_id' => $account->id, 'type' => 'debit', 'source' => 'order_cost', 'amount' => 200, 'credit' => 0, 'debit' => 200, 'running_balance' => 300, 'created_at' => '2026-07-01']);

    $response = $this->actingAs($this->user)->get(route('reports.cash-flow'));

    $response->assertOk();
    $response->assertViewHas('totalIn', 500.0);
    $response->assertViewHas('totalOut', 200.0);
    $response->assertViewHas('net', 300.0);
});

test('cash flow lists every ledger entry with its account and the combined balance', function () {
    $type = AccountType::create(['name' => 'Bank']);
    $cash = PaymentAccount::create(['name' => 'Cash in Hand', 'account_type_id' => $type->id, 'balance' => 300, 'is_active' => true]);
    $bank = PaymentAccount::create(['name' => 'Quick Shifter Bank Account', 'account_type_id' => $type->id, 'balance' => 700, 'is_active' => true]);

    Transaction::create(['payment_account_id' => $cash->id, 'type' => 'credit', 'source' => 'order_payment', 'amount' => 500, 'credit' => 500, 'debit' => 0, 'running_balance' => 500, 'created_at' => '2026-07-01']);
    Transaction::create(['payment_account_id' => $cash->id, 'type' => 'debit', 'source' => 'order_cost', 'amount' => 200, 'credit' => 0, 'debit' => 200, 'running_balance' => 300, 'created_at' => '2026-07-02']);
    Transaction::create(['payment_account_id' => $bank->id, 'type' => 'credit', 'source' => 'deposit', 'amount' => 700, 'credit' => 700, 'debit' => 0, 'running_balance' => 700, 'created_at' => '2026-07-03']);

    $response = $this->actingAs($this->user)->get(route('reports.cash-flow'));

    $response->assertOk();
    $response->assertSee('Quick Shifter Bank Account');
    $response->assertSee('Cash in Hand');

    $ledger = $response->viewData('transactions');
    expect($ledger)->toHaveCount(3);
    // Newest first, and the combined balance walks the whole ledger: 500 - 200 + 700.
    expect($ledger->first()->total_balance)->toBe(1000.0);
    expect($ledger->last()->total_balance)->toBe(500.0);
    // The per-account balance is the one already stored on the row.
    expect((float) $ledger->first()->running_balance)->toBe(700.0);
});

test('cash flow narrows the ledger by account, type and source', function () {
    $type = AccountType::create(['name' => 'Bank']);
    $cash = PaymentAccount::create(['name' => 'Cash in Hand', 'account_type_id' => $type->id, 'balance' => 300, 'is_active' => true]);
    $bank = PaymentAccount::create(['name' => 'Bank A', 'account_type_id' => $type->id, 'balance' => 700, 'is_active' => true]);

    Transaction::create(['payment_account_id' => $cash->id, 'type' => 'credit', 'source' => 'order_payment', 'amount' => 500, 'credit' => 500, 'debit' => 0, 'running_balance' => 500, 'created_at' => '2026-07-01']);
    Transaction::create(['payment_account_id' => $cash->id, 'type' => 'debit', 'source' => 'order_cost', 'amount' => 200, 'credit' => 0, 'debit' => 200, 'running_balance' => 300, 'created_at' => '2026-07-02']);
    Transaction::create(['payment_account_id' => $bank->id, 'type' => 'credit', 'source' => 'deposit', 'amount' => 700, 'credit' => 700, 'debit' => 0, 'running_balance' => 700, 'created_at' => '2026-07-03']);

    $byAccount = $this->actingAs($this->user)->get(route('reports.cash-flow', ['payment_account_id' => $cash->id]));
    expect($byAccount->viewData('transactions'))->toHaveCount(2);
    $byAccount->assertViewHas('totalIn', 500.0);
    $byAccount->assertViewHas('totalOut', 200.0);

    $byType = $this->actingAs($this->user)->get(route('reports.cash-flow', ['transaction_type' => 'debit']));
    expect($byType->viewData('transactions'))->toHaveCount(1);
    $byType->assertViewHas('totalIn', 0.0);

    $bySource = $this->actingAs($this->user)->get(route('reports.cash-flow', ['source' => 'deposit']));
    expect($bySource->viewData('transactions'))->toHaveCount(1);
    $bySource->assertViewHas('totalIn', 700.0);

    $byDate = $this->actingAs($this->user)->get(route('reports.cash-flow', ['from' => '2026-07-02', 'to' => '2026-07-03']));
    expect($byDate->viewData('transactions'))->toHaveCount(2);
});

test('cash flow offers edit and delete actions on manual entries only', function () {
    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash', 'account_type_id' => $type->id, 'balance' => 300, 'is_active' => true]);

    $deposit = Transaction::create(['payment_account_id' => $account->id, 'type' => 'credit', 'source' => 'deposit', 'amount' => 500, 'credit' => 500, 'debit' => 0, 'running_balance' => 500, 'description' => 'Deposit', 'created_at' => '2026-07-01']);
    $system = Transaction::create(['payment_account_id' => $account->id, 'type' => 'debit', 'source' => 'order_cost', 'amount' => 200, 'credit' => 0, 'debit' => 200, 'running_balance' => 300, 'description' => 'Order cost', 'created_at' => '2026-07-02']);

    $response = $this->actingAs($this->user)->get(route('reports.cash-flow'));

    $response->assertOk();
    $response->assertSee(route('transaction.delete', $deposit->id), false);
    $response->assertDontSee(route('transaction.delete', $system->id), false);
});

test('cash flow hides the action column from a role that cannot manage accounts', function () {
    $viewer = userWithPermissions(['reports.cash-flow'], 'reports-only');

    $type = AccountType::create(['name' => 'Cash']);
    $account = PaymentAccount::create(['name' => 'Cash', 'account_type_id' => $type->id, 'balance' => 500, 'is_active' => true]);
    $deposit = Transaction::create(['payment_account_id' => $account->id, 'type' => 'credit', 'source' => 'deposit', 'amount' => 500, 'credit' => 500, 'debit' => 0, 'running_balance' => 500, 'description' => 'Deposit', 'created_at' => '2026-07-01']);

    $response = $this->actingAs($viewer)->get(route('reports.cash-flow'));

    $response->assertOk();
    $response->assertSee('Total Balance');
    $response->assertDontSee(route('transaction.delete', $deposit->id), false);
});

test('each report is granted on its own', function () {
    // This role may read the P&L and nothing else.
    $user = userWithPermissions(['reports.profit-loss'], 'pnl-only');

    $this->actingAs($user)->get(route('reports.profit-loss'))->assertOk();
    $this->actingAs($user)->get(route('reports.receivables'))->assertForbidden();
    $this->actingAs($user)->get(route('reports.balance-sheet'))->assertForbidden();
    $this->actingAs($user)->get(route('reports.cash-flow'))->assertForbidden();
    $this->actingAs($user)->get(route('reports.warehouse-summary'))->assertForbidden();
});

test('a role with no report permissions cannot access reports', function () {
    $user = userWithPermissions([], 'no-reports');

    $this->actingAs($user)->get(route('reports.profit-loss'))->assertForbidden();
});
