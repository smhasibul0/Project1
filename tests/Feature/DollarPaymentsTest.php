<?php

use App\Models\Container;
use App\Models\DollarTransaction;
use App\Models\Loan;
use App\Models\OfficeCostType;
use App\Models\OfficeExpense;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseExpense;
use App\Models\WarehouseStaff;
use App\Support\DollarLedger;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Every place that pays from (or into) an account can do it in dollars: out of the
 * dollars an account holds at what they cost, or into them at the rate given.
 */
beforeEach(function () {
    $this->user = adminUser();
    $this->bank = PaymentAccount::factory()->create(['name' => 'Janata Bank', 'balance' => 100000]);
    // $1,000 held at ৳122 each.
    DB::transaction(fn () => DollarLedger::receive($this->bank, 1000, 122, 'deposit'));
});

/**
 * The bank's balances, as [taka, dollars].
 *
 * @return array{0: string, 1: string}
 */
function bankBalances(PaymentAccount $account): array
{
    $account->refresh();

    return [(string) $account->balance, (string) $account->usd_balance];
}

function portalUser(Warehouse $warehouse): User
{
    return userWithPermissions(PermissionCatalog::group('Warehouse Portal'), 'warehouse', ['warehouse_id' => $warehouse->id]);
}

test('an order cost paid in dollars is costed at what they cost, and deleting it gives them back', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('order.cost.store', $order->id), [
        'title' => 'Sea freight', 'currency' => 'USD', 'usd_amount' => 300, 'payment_account_id' => $this->bank->id, 'cost_date' => '2026-10-03',
    ])->assertSessionHas('success', 'Cost of 36,600.00 recorded.');

    $cost = $order->costs()->sole();
    expect($cost->amount)->toEqual('36600.00')->and($cost->dollarNote())->toBe('$300.00 @ 122');
    expect(bankBalances($this->bank))->toBe(['100000.00', '700.00']); // no taka moved
    expect(Transaction::where('source', 'order_cost')->count())->toBe(0);
    expect($order->fresh()->total_expense)->toEqual('36600.00');

    $this->delete(route('order.cost.delete', [$order->id, $cost->id]))->assertSessionHas('success');
    expect(bankBalances($this->bank))->toBe(['100000.00', '1000.00']);
});

test('a customer payment received in dollars settles their taka and keeps the dollars', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('order.payment', $order->id), [
        'payment_date' => '2026-10-03', 'currency' => 'USD', 'usd_amount' => 100, 'usd_rate' => 124, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHas('success', 'Payment of 12,400.00 recorded.');

    expect($order->payments()->sole()->amount)->toEqual('12400.00');
    expect(bankBalances($this->bank))->toBe(['100000.00', '1100.00']);
    expect($this->bank->usd_cost)->toEqual('134400.00'); // 122,000 + 100 × 124
});

test('a container cost can be paid in dollars', function () {
    $container = Container::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('container.cost.store', $container->id), [
        'title' => 'Port handling', 'currency' => 'USD', 'usd_amount' => 50, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHas('success');

    expect($container->costs()->sole()->amount)->toEqual('6100.00');
    expect(bankBalances($this->bank))->toBe(['100000.00', '950.00']);
});

test('an expense is paid in dollars up to what is owed', function () {
    $type = OfficeCostType::create(['name' => 'Overseas agent fee', 'nature' => 'variable']);
    $this->actingAs($this->user);

    // Added with a first payment in dollars.
    $this->post(route('office.expense.store'), [
        'office_cost_type_id' => $type->id, 'amount' => 20000, 'expense_date' => '2026-10-03',
        'currency' => 'USD', 'usd_amount' => 100, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    $expense = OfficeExpense::sole();
    expect($expense->payments()->sole()->amount)->toEqual('12200.00');

    // The rest: $70 would come to ৳8,540 — more than the ৳7,800 owed.
    $this->post(route('office.expense.payments.store', $expense->id), [
        'paid_on' => '2026-10-04', 'currency' => 'USD', 'usd_amount' => 70, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasErrors(['usd_amount' => '$70.00 comes to ৳8,540.00 at what the dollars cost — more than the ৳7,800.00 owed.']);

    $this->post(route('office.expense.payments.store', $expense->id), [
        'paid_on' => '2026-10-04', 'currency' => 'USD', 'usd_amount' => 50, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    expect($expense->fresh('payments')->paidTotal())->toEqual(18300.0);
    expect(bankBalances($this->bank))->toBe(['100000.00', '850.00']);

    // Deleting the expense gives back every dollar it took.
    $this->delete(route('office.expense.delete', $expense->id));
    expect(bankBalances($this->bank))->toBe(['100000.00', '1000.00']);
});

test('a warehouse expense and a salary can be paid in dollars', function () {
    $warehouse = Warehouse::factory()->create();
    $this->actingAs(portalUser($warehouse));

    $this->post(route('warehouse.expenses.store'), [
        'amount' => 5000, 'expense_date' => '2026-10-03',
        'currency' => 'USD', 'usd_amount' => 20, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();
    expect(WarehouseExpense::sole()->payments()->sole()->amount)->toEqual('2440.00');

    $staff = WarehouseStaff::create(['warehouse_id' => $warehouse->id, 'name' => 'Karim', 'monthly_salary' => 10000]);
    $this->post(route('warehouse.staff.salary.store', $staff->id), [
        'salary_month' => '2026-10', 'payment_date' => '2026-10-05',
        'currency' => 'USD', 'usd_amount' => 50, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    $salary = $staff->salaryPayments()->sole();
    expect($salary->amount)->toEqual('6100.00')->and($salary->dollarNote())->toBe('$50.00 @ 122');
    expect(bankBalances($this->bank))->toBe(['100000.00', '930.00']);

    // More than the month's due is refused.
    $this->post(route('warehouse.staff.salary.store', $staff->id), [
        'salary_month' => '2026-10', 'payment_date' => '2026-10-06',
        'currency' => 'USD', 'usd_amount' => 40, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasErrors('usd_amount');
});

test('money borrowed in dollars comes in at the rate given and is repaid out of the dollars', function () {
    $this->actingAs($this->user);

    $this->post(route('loan.store', 'borrowed'), [
        'counterparty' => 'Dubai Partner', 'counterparty_type' => 'company', 'principal' => 1, 'interest_type' => 'none',
        'start_date' => '2026-10-01', 'payment_account_id' => $this->bank->id,
        'currency' => 'USD', 'usd_amount' => 500, 'usd_rate' => 124,
    ])->assertSessionHasNoErrors();

    $loan = Loan::sole();
    expect($loan->principal)->toEqual('62000.00')->and($loan->dollarNote())->toBe('$500.00 @ 124');
    expect(bankBalances($this->bank))->toBe(['100000.00', '1500.00']);
    expect(Transaction::where('source', 'loan_received')->count())->toBe(0);

    // Repaid in dollars at what they cost: (122,000 + 62,000) / 1,500 = 122.6667 each.
    $this->post(route('loan.payments.store', $loan->id), [
        'paid_on' => '2026-10-10', 'currency' => 'USD', 'usd_amount' => 300, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    expect($loan->payments()->sole()->amount)->toEqual('36800.00');
    expect(bankBalances($this->bank))->toBe(['100000.00', '1200.00']);
});

test('money lent in dollars goes out of the dollars and comes back in at the rate given', function () {
    $this->actingAs($this->user);

    $this->post(route('loan.store', 'lent'), [
        'counterparty' => 'Shipper agent', 'counterparty_type' => 'company', 'principal' => 1, 'interest_type' => 'none',
        'start_date' => '2026-10-01', 'payment_account_id' => $this->bank->id,
        'currency' => 'USD', 'usd_amount' => 400,
    ])->assertSessionHasNoErrors();

    $loan = Loan::sole();
    expect($loan->principal)->toEqual('48800.00');
    expect(bankBalances($this->bank))->toBe(['100000.00', '600.00']);

    $this->post(route('loan.payments.store', $loan->id), [
        'paid_on' => '2026-10-10', 'currency' => 'USD', 'usd_amount' => 200, 'usd_rate' => 123, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    expect($loan->payments()->sole()->amount)->toEqual('24600.00');
    expect(bankBalances($this->bank))->toBe(['100000.00', '800.00']);

    // Deleting the loan undoes both, in dollars.
    $this->delete(route('loan.payments.delete', [$loan->id, $loan->payments()->sole()->id]));
    $this->delete(route('loan.delete', $loan->id));
    expect(bankBalances($this->bank))->toBe(['100000.00', '1000.00']);
});

test('dollars can be deposited and transferred from the accounts list', function () {
    $other = PaymentAccount::factory()->create(['name' => 'City Bank']);
    $this->actingAs($this->user);

    $this->post(route('payment.account.deposit'), [
        'account_id' => $this->bank->id, 'deposit_date' => '2026-10-03', 'currency' => 'USD', 'usd_amount' => 200, 'usd_rate' => 125,
    ])->assertSessionHas('success', '$200.00 deposited into Janata Bank.');
    expect(bankBalances($this->bank))->toBe(['100000.00', '1200.00']);

    $this->post(route('payment.account.fund.transfer'), [
        'from_account_id' => $this->bank->id, 'to_account_id' => $other->id, 'transfer_date' => '2026-10-04',
        'currency' => 'USD', 'usd_amount' => 600,
    ])->assertSessionHas('success');

    expect(bankBalances($this->bank))->toBe(['100000.00', '600.00']);
    expect(bankBalances($other))->toBe(['0.00', '600.00']);
});

test('paying in dollars needs the account they come from, and enough of them', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('order.cost.store', $order->id), ['title' => 'Freight', 'currency' => 'USD', 'usd_amount' => 10])
        ->assertSessionHasErrors('payment_account_id');

    $this->post(route('order.cost.store', $order->id), [
        'title' => 'Freight', 'currency' => 'USD', 'usd_amount' => 1500, 'payment_account_id' => $this->bank->id,
    ])->assertSessionHasErrors(['usd_amount' => 'Janata Bank holds $1,000.00 — $1,500.00 are needed.']);

    expect($order->costs()->count())->toBe(0);
    expect(DollarTransaction::count())->toBe(1);
});

test('payment forms offer taka or dollars, with what each account\'s dollars cost', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->user);

    $this->get(route('order.show', $order->id))
        ->assertOk()
        ->assertSee('$ Dollars')
        ->assertSee('data-usd-rate="122"', false);
});
