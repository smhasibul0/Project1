<?php

use App\Models\AccountType;
use App\Models\Loan;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
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

test('a user without reports.view cannot access reports', function () {
    $role = Role::firstOrCreate(['slug' => 'no-reports'], ['name' => 'No Reports']);
    // ensure the permission exists but is NOT attached to this role
    Permission::firstOrCreate(['key' => 'reports.view'], ['name' => 'View reports', 'group' => 'Finance']);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)->get(route('reports.profit-loss'))->assertForbidden();
});
