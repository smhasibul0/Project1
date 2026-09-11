<?php

use App\Models\ExpenseCategory;
use App\Models\OfficeCostType;
use App\Models\OfficeExpense;
use App\Models\OfficeExpensePayment;
use App\Models\PaymentAccount;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\OfficeCostTypeSeeder;

beforeEach(function () {
    $this->user = adminUser();
    $this->rent = OfficeCostType::create(['name' => 'Office Rent', 'nature' => 'fixed']);
    $this->stationery = OfficeCostType::create(['name' => 'Stationery', 'nature' => 'variable']);
});

/**
 * Book an expense straight to the database, bypassing the HTTP layer.
 */
function officeExpense(OfficeCostType $type, float $amount, string $date): OfficeExpense
{
    return OfficeExpense::create([
        'office_cost_type_id' => $type->id,
        'amount' => $amount,
        'expense_date' => $date,
    ]);
}

test('an expense inherits fixed or variable from its cost type', function () {
    expect(officeExpense($this->rent, 25000, '2026-09-01')->isFixed())->toBeTrue();
    expect(officeExpense($this->stationery, 1200, '2026-09-03')->isFixed())->toBeFalse();
});

test('an expense whose cost type was deleted falls back to variable', function () {
    $expense = officeExpense($this->rent, 25000, '2026-09-01');
    $this->rent->delete();

    expect($expense->fresh()->nature())->toBe('variable');
});

test('the monthly view totals fixed and variable separately', function () {
    officeExpense($this->rent, 25000, '2026-09-01');
    officeExpense($this->stationery, 1200, '2026-09-03');
    officeExpense($this->stationery, 800, '2026-09-20');
    // Another month's cost must stay out of September's totals.
    officeExpense($this->rent, 25000, '2026-08-01');

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    expect($response->viewData('fixedTotal'))->toEqual(25000.0);
    expect($response->viewData('variableTotal'))->toEqual(2000.0);
    expect($response->viewData('total'))->toEqual(27000.0);
    expect($response->viewData('expenses'))->toHaveCount(3);
});

test('the month falls back to the current one when the parameter is junk', function () {
    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => 'not-a-month']));

    $response->assertOk();
    expect($response->viewData('month')->format('Y-m'))->toBe(now()->format('Y-m'));
});

test('recording an expense with a payment debits the account', function () {
    $account = PaymentAccount::factory()->create(['balance' => 50000]);

    $this->actingAs($this->user)->post(route('office.expense.store'), [
        'office_cost_type_id' => $this->rent->id,
        'amount' => 25000,
        'expense_date' => '2026-09-01',
        'payment_amount' => 10000,
        'paid_on' => '2026-09-02',
        'payment_method' => 'Bank Transfer',
        'payment_account_id' => $account->id,
    ])->assertSessionHasNoErrors();

    $expense = OfficeExpense::with('payments')->firstOrFail();
    expect($expense->paidTotal())->toEqual(10000.0);
    expect($expense->dueTotal())->toEqual(15000.0);
    expect($expense->paymentStatus())->toBe('partial');
    expect($account->fresh()->balance)->toEqual('40000.00');

    $transaction = Transaction::where('transactionable_type', OfficeExpensePayment::class)->firstOrFail();
    expect($transaction->debit)->toEqual('10000.00');
    expect($transaction->source)->toBe('office_expense');
});

test('an expense saved without a payment stays fully due', function () {
    $this->actingAs($this->user)->post(route('office.expense.store'), [
        'office_cost_type_id' => $this->stationery->id,
        'amount' => 1200,
        'expense_date' => '2026-09-03',
    ])->assertSessionHasNoErrors();

    $expense = OfficeExpense::with('payments')->firstOrFail();
    expect($expense->paymentStatus())->toBe('due');
    expect($expense->dueTotal())->toEqual(1200.0);
});

test('paying the balance in instalments settles the expense', function () {
    $account = PaymentAccount::factory()->create(['balance' => 50000]);
    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    foreach ([15000, 10000] as $amount) {
        $this->actingAs($this->user)->post(route('office.expense.payments.store', $expense->id), [
            'amount' => $amount,
            'paid_on' => '2026-09-05',
            'payment_account_id' => $account->id,
        ])->assertSessionHasNoErrors();
    }

    $expense = $expense->fresh('payments');
    expect($expense->payments)->toHaveCount(2);
    expect($expense->paymentStatus())->toBe('paid');
    expect($expense->dueTotal())->toEqual(0.0);
    expect($account->fresh()->balance)->toEqual('25000.00');
});

test('a payment larger than the outstanding due is rejected', function () {
    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    $this->actingAs($this->user)->post(route('office.expense.payments.store', $expense->id), [
        'amount' => 25001,
        'paid_on' => '2026-09-05',
    ])->assertSessionHasErrors('amount');

    expect($expense->fresh('payments')->payments)->toHaveCount(0);
});

test('an expense total cannot be edited below what has already been paid', function () {
    $expense = officeExpense($this->rent, 25000, '2026-09-01');
    $expense->payments()->create(['amount' => 20000, 'paid_on' => '2026-09-05']);

    $this->actingAs($this->user)->put(route('office.expense.update', $expense->id), [
        'office_cost_type_id' => $this->rent->id,
        'amount' => 15000,
        'expense_date' => '2026-09-01',
    ])->assertSessionHasErrors('amount');

    expect($expense->fresh()->amount)->toEqual('25000.00');
});

test('deleting a payment credits its account back', function () {
    $account = PaymentAccount::factory()->create(['balance' => 50000]);
    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    $this->actingAs($this->user)->post(route('office.expense.payments.store', $expense->id), [
        'amount' => 10000,
        'paid_on' => '2026-09-05',
        'payment_account_id' => $account->id,
    ]);
    expect($account->fresh()->balance)->toEqual('40000.00');

    $payment = $expense->fresh('payments')->payments->first();
    $this->actingAs($this->user)->delete(route('office.expense.payments.delete', [$expense->id, $payment->id]));

    expect($account->fresh()->balance)->toEqual('50000.00');
    expect(Transaction::where('transactionable_type', OfficeExpensePayment::class)->count())->toBe(0);
});

test('deleting an expense reverses every payment it holds', function () {
    $account = PaymentAccount::factory()->create(['balance' => 50000]);
    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    foreach ([15000, 10000] as $amount) {
        $this->actingAs($this->user)->post(route('office.expense.payments.store', $expense->id), [
            'amount' => $amount,
            'paid_on' => '2026-09-05',
            'payment_account_id' => $account->id,
        ]);
    }
    expect($account->fresh()->balance)->toEqual('25000.00');

    $this->actingAs($this->user)->delete(route('office.expense.delete', $expense->id));

    expect($account->fresh()->balance)->toEqual('50000.00');
    expect(OfficeExpense::count())->toBe(0);
    expect(OfficeExpensePayment::count())->toBe(0);
});

test('a cost type can be added, re-classified and removed', function () {
    $this->actingAs($this->user)->post(route('office.cost.type.store'), [
        'name' => 'Internet Bill',
        'nature' => 'fixed',
    ])->assertSessionHasNoErrors();

    $type = OfficeCostType::where('name', 'Internet Bill')->firstOrFail();
    expect($type->isFixed())->toBeTrue();
    expect($type->is_active)->toBeTrue();

    $this->actingAs($this->user)->put(route('office.cost.type.update', $type->id), [
        'name' => 'Internet Bill',
        'nature' => 'variable',
        'is_active' => 0,
    ])->assertSessionHasNoErrors();

    $type = $type->fresh();
    expect($type->isFixed())->toBeFalse();
    expect($type->is_active)->toBeFalse();

    $this->actingAs($this->user)->delete(route('office.cost.type.delete', $type->id));
    expect(OfficeCostType::find($type->id))->toBeNull();
});

test('a cost type nature outside fixed or variable is rejected', function () {
    $this->actingAs($this->user)->post(route('office.cost.type.store'), [
        'name' => 'Mystery Cost',
        'nature' => 'occasional',
    ])->assertSessionHasErrors('nature');

    expect(OfficeCostType::where('name', 'Mystery Cost')->exists())->toBeFalse();
});

test('a cost type is filed under a category and sub-category', function () {
    $utilities = ExpenseCategory::create(['name' => 'Utilities']);
    $electricity = ExpenseCategory::create(['name' => 'Electricity', 'parent_id' => $utilities->id]);

    $this->actingAs($this->user)->post(route('office.cost.type.store'), [
        'name' => 'Electricity Bill',
        'expense_category_id' => $electricity->id,
        'nature' => 'fixed',
    ])->assertSessionHasNoErrors();

    $type = OfficeCostType::where('name', 'Electricity Bill')->firstOrFail();
    expect($type->categoryName())->toBe('Utilities');
    expect($type->subCategoryName())->toBe('Electricity');
    expect($type->categoryPath())->toBe('Utilities · Electricity');
});

test('a cost type filed on a bare category has no sub-category', function () {
    $office = ExpenseCategory::create(['name' => 'Office']);
    $type = OfficeCostType::create(['name' => 'Sundries', 'nature' => 'variable', 'expense_category_id' => $office->id]);

    expect($type->categoryName())->toBe('Office');
    expect($type->subCategoryName())->toBeNull();
    expect($type->categoryPath())->toBe('Office');
});

test('an uncategorised cost type reports no category at all', function () {
    expect($this->stationery->categoryName())->toBeNull();
    expect($this->stationery->categoryPath())->toBeNull();
});

test('an expense inherits its category and sub-category from the cost type', function () {
    $rent = ExpenseCategory::create(['name' => 'Rent']);
    $building = ExpenseCategory::create(['name' => 'Building', 'parent_id' => $rent->id]);
    $this->rent->update(['expense_category_id' => $building->id]);

    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    expect($expense->categoryName())->toBe('Rent');
    expect($expense->subCategoryName())->toBe('Building');
    expect($expense->categoryPath())->toBe('Rent · Building');
});

test('an expense whose cost type was deleted reports no category', function () {
    $rent = ExpenseCategory::create(['name' => 'Rent']);
    $this->rent->update(['expense_category_id' => $rent->id]);
    $expense = officeExpense($this->rent, 25000, '2026-09-01');
    $this->rent->delete();

    expect($expense->fresh()->categoryName())->toBeNull();
});

test('re-categorising a cost type moves every expense booked against it', function () {
    $utilities = ExpenseCategory::create(['name' => 'Utilities']);
    $water = ExpenseCategory::create(['name' => 'Water', 'parent_id' => $utilities->id]);
    $gas = ExpenseCategory::create(['name' => 'Gas', 'parent_id' => $utilities->id]);
    $this->rent->update(['expense_category_id' => $water->id]);
    $expense = officeExpense($this->rent, 25000, '2026-09-01');

    $this->actingAs($this->user)->put(route('office.cost.type.update', $this->rent->id), [
        'name' => 'Office Rent',
        'expense_category_id' => $gas->id,
        'nature' => 'fixed',
    ])->assertSessionHasNoErrors();

    expect($expense->fresh()->subCategoryName())->toBe('Gas');
});

test('a category that does not exist is rejected on a cost type', function () {
    $this->actingAs($this->user)->post(route('office.cost.type.store'), [
        'name' => 'Phantom Cost',
        'expense_category_id' => 9999,
        'nature' => 'variable',
    ])->assertSessionHasErrors('expense_category_id');

    expect(OfficeCostType::where('name', 'Phantom Cost')->exists())->toBeFalse();
});

test('clearing the category on a cost type leaves it uncategorised', function () {
    $office = ExpenseCategory::create(['name' => 'Office']);
    $this->stationery->update(['expense_category_id' => $office->id]);

    $this->actingAs($this->user)->put(route('office.cost.type.update', $this->stationery->id), [
        'name' => 'Stationery',
        'nature' => 'variable',
    ])->assertSessionHasNoErrors();

    expect($this->stationery->fresh()->expense_category_id)->toBeNull();
});

test('the expenses page lists every active cost type to pick from', function () {
    $utilities = ExpenseCategory::create(['name' => 'Utilities']);
    $this->rent->update(['expense_category_id' => $utilities->id]);
    // An inactive type must stay out of the picker.
    OfficeCostType::create(['name' => 'Retired Cost', 'nature' => 'variable', 'is_active' => false]);

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    expect($response->viewData('costTypes')->pluck('name')->all())
        ->toEqual(['Office Rent', 'Stationery']);
    // Each pickable row carries its name and the category it reports under.
    $response->assertSee('data-label="Office Rent"', false);
    $response->assertSee('data-hint="Fixed · Utilities"', false);
    $response->assertDontSee('Retired Cost');
});

test('the expenses page points at cost types when none exist yet', function () {
    OfficeCostType::query()->delete();

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    expect($response->viewData('costTypes'))->toHaveCount(0);
    $response->assertSee('No active cost types yet');
    $response->assertSee('Add Cost Type First');
});

test('the seeded starter cost types all carry a category', function () {
    OfficeCostType::query()->delete();

    $this->seed(OfficeCostTypeSeeder::class);

    $types = OfficeCostType::with('category.parent')->get();
    expect($types)->not->toBeEmpty();
    expect($types->every(fn (OfficeCostType $t) => $t->categoryName() !== null))->toBeTrue();
    expect(OfficeCostType::where('name', 'Office Rent')->firstOrFail()->categoryPath())->toBe('Rent · Building');
});

test('the cost type picker is searchable by name, category and nature', function () {
    $utilities = ExpenseCategory::create(['name' => 'Utilities']);
    $electricity = ExpenseCategory::create(['name' => 'Electricity', 'parent_id' => $utilities->id]);
    $this->rent->update(['name' => 'Electricity Bill', 'expense_category_id' => $electricity->id]);

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    // The type-ahead input replaces the plain dropdown entirely.
    $response->assertSee('cts-search', false);
    $response->assertDontSee('<select class="form-control" name="office_cost_type_id"', false);
    // One lowercase key per row covers the name, its category path and its nature.
    $response->assertSee('data-search="electricity bill utilities · electricity fixed"', false);
});

test('the cost type picker opens on the expense it is editing', function () {
    $expense = officeExpense($this->stationery, 1200, '2026-09-03');

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    $response->assertSee('name="office_cost_type_id" value="'.$this->stationery->id.'" class="cts-value"', false);
});

test('the cost type picker keeps an inactive type pickable while an expense uses it', function () {
    officeExpense($this->stationery, 1200, '2026-09-03');
    $this->stationery->update(['is_active' => false]);

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    // Out of the picker for new expenses, still selectable on the one that uses it.
    expect($response->viewData('costTypes')->pluck('name')->all())->toEqual(['Office Rent']);
    $response->assertSee('data-id="'.$this->stationery->id.'"', false);
});

test('the expense list offers a filter on every column worth narrowing by', function () {
    officeExpense($this->rent, 25000, '2026-09-01');

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));

    $response->assertOk();
    // The shared table builds a dropdown per data-filter header.
    foreach (['Cost Type', 'Category', 'Sub-category', 'Nature', 'Payment Status'] as $filter) {
        $response->assertSee('data-filter="'.$filter.'"', false);
    }
});

test('office expenses are closed to users without the permission', function () {
    $this->actingAs(User::factory()->create())->get(route('office.expenses'))->assertForbidden();
});

test('a monthly amount is only kept on fixed cost types', function () {
    $this->actingAs($this->user)->post(route('office.cost.type.store'), [
        'name' => 'Internet Bill',
        'nature' => 'fixed',
        'monthly_amount' => 3000,
    ])->assertSessionHasNoErrors();

    $type = OfficeCostType::where('name', 'Internet Bill')->firstOrFail();
    expect($type->monthly_amount)->toEqual('3000.00');

    // Re-classifying it as variable clears the amount, keeping it out of generation.
    $this->actingAs($this->user)->put(route('office.cost.type.update', $type->id), [
        'name' => 'Internet Bill',
        'nature' => 'variable',
        'monthly_amount' => 3000,
    ])->assertSessionHasNoErrors();

    expect($type->fresh()->monthly_amount)->toBeNull();
});

test('generating a month books every fixed cost that carries an amount', function () {
    $this->rent->update(['monthly_amount' => 25000]);
    $internet = OfficeCostType::create(['name' => 'Internet', 'nature' => 'fixed', 'monthly_amount' => 3000]);
    // No standard amount, so generation must skip it.
    OfficeCostType::create(['name' => 'Misc Fixed', 'nature' => 'fixed']);
    // Variable types are never generated, even with an amount on the row.
    $this->stationery->update(['monthly_amount' => 999]);

    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-09'])
        ->assertSessionHasNoErrors();

    $expenses = OfficeExpense::all();
    expect($expenses)->toHaveCount(2);
    expect($expenses->pluck('office_cost_type_id')->sort()->values()->all())
        ->toEqual(collect([$this->rent->id, $internet->id])->sort()->values()->all());
    expect($expenses->sum(fn ($e) => (float) $e->amount))->toEqual(28000.0);

    // Everything lands on the first of the target month, unpaid.
    expect($expenses->every(fn ($e) => $e->expense_date->toDateString() === '2026-09-01'))->toBeTrue();
    expect($expenses->every(fn ($e) => $e->fresh('payments')->paymentStatus() === 'due'))->toBeTrue();
});

test('generating the same month twice does not duplicate or overwrite', function () {
    $this->rent->update(['monthly_amount' => 25000]);

    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-09']);

    // The real rent came in higher this month; the edit must survive a re-run.
    $expense = OfficeExpense::firstOrFail();
    $expense->update(['amount' => 27000]);

    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-09']);

    expect(OfficeExpense::count())->toBe(1);
    expect(OfficeExpense::firstOrFail()->amount)->toEqual('27000.00');
});

test('generation only fills the month it is asked for', function () {
    $this->rent->update(['monthly_amount' => 25000]);

    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-09']);
    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-10']);

    expect(OfficeExpense::count())->toBe(2);
    expect(OfficeExpense::orderBy('expense_date')->pluck('expense_date')
        ->map(fn ($d) => $d->toDateString())->all())
        ->toEqual(['2026-09-01', '2026-10-01']);
});

test('the month view flags the fixed costs it is still missing', function () {
    $this->rent->update(['monthly_amount' => 25000]);
    OfficeCostType::create(['name' => 'Internet', 'nature' => 'fixed', 'monthly_amount' => 3000]);

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));
    expect($response->viewData('pendingFixed'))->toHaveCount(2);
    expect($response->viewData('pendingFixedTotal'))->toEqual(28000.0);

    $this->actingAs($this->user)->post(route('office.expense.generate'), ['month' => '2026-09']);

    $response = $this->actingAs($this->user)->get(route('office.expenses', ['month' => '2026-09']));
    expect($response->viewData('pendingFixed'))->toHaveCount(0);
    expect($response->viewData('pendingFixedTotal'))->toEqual(0.0);
});

test('office expenses reduce net profit in the P&L', function () {
    officeExpense($this->rent, 25000, '2026-09-01');
    officeExpense($this->stationery, 2000, '2026-09-15');
    // Outside the reporting window, so it must not be counted.
    officeExpense($this->rent, 25000, '2026-08-01');

    $response = $this->actingAs($this->user)->get(route('reports.profit-loss', [
        'from' => '2026-09-01',
        'to' => '2026-09-30',
    ]));

    $response->assertOk();
    $operating = $response->viewData('operating');
    expect($operating['office_expenses'])->toEqual(27000.0);
    expect($operating['total'])->toEqual(27000.0);
    expect($response->viewData('netProfit'))->toEqual(-27000.0);
});

test('the cost type list can be searched by name or category', function () {
    $utilities = ExpenseCategory::create(['name' => 'Utilities']);
    $electricity = ExpenseCategory::create(['name' => 'Electricity', 'parent_id' => $utilities->id]);
    OfficeCostType::create([
        'name' => 'Electricity Bill',
        'nature' => 'fixed',
        'expense_category_id' => $electricity->id,
    ]);
    OfficeCostType::create(['name' => 'Courier Charges', 'nature' => 'variable']);

    // A name match keeps only the matching type.
    $this->actingAs($this->user)->get(route('office.cost.types', ['q' => 'courier']))
        ->assertSee('Courier Charges')
        ->assertDontSee('Electricity Bill');

    // The sub-category and its parent both find the type filed under them.
    foreach (['Electricity', 'Utilities'] as $term) {
        $this->actingAs($this->user)->get(route('office.cost.types', ['q' => $term]))
            ->assertSee('Electricity Bill')
            ->assertDontSee('Courier Charges');
    }

    // An unmatched term empties both tables and says so.
    $this->actingAs($this->user)->get(route('office.cost.types', ['q' => 'zzz']))
        ->assertDontSee('Courier Charges')
        ->assertDontSee('Electricity Bill')
        ->assertSee('Nothing matches', false);

    // No term at all still lists everything.
    $this->actingAs($this->user)->get(route('office.cost.types'))
        ->assertSee('Courier Charges')
        ->assertSee('Electricity Bill');
});

test('the cost type list can be filtered by nature', function () {
    OfficeCostType::create(['name' => 'Generator Fuel', 'nature' => 'fixed']);
    OfficeCostType::create(['name' => 'Pantry Supplies', 'nature' => 'variable']);

    // Picking a nature drops the other card from the page entirely.
    $this->actingAs($this->user)->get(route('office.cost.types', ['nature' => 'fixed']))
        ->assertSee('Generator Fuel')
        ->assertDontSee('Pantry Supplies');

    $this->actingAs($this->user)->get(route('office.cost.types', ['nature' => 'variable']))
        ->assertSee('Pantry Supplies')
        ->assertDontSee('Generator Fuel');

    // The search box and the dropdown narrow together.
    $this->actingAs($this->user)->get(route('office.cost.types', ['q' => 'fuel', 'nature' => 'variable']))
        ->assertDontSee('Generator Fuel')
        ->assertSee('Nothing matches', false);

    // A nature outside fixed or variable is ignored rather than emptying the page.
    $response = $this->actingAs($this->user)->get(route('office.cost.types', ['nature' => 'sideways']));
    expect($response->viewData('nature'))->toBe('');
    $response->assertSee('Generator Fuel')->assertSee('Pantry Supplies');
});
