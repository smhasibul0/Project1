<?php

use App\Models\AccountType;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A screen must not offer what the role cannot do: unticking an action in the roles matrix
 * takes its button off the page, not just 403s the request.
 */
test('the customer list hides add, edit and delete from a view-only role', function () {
    Contact::factory()->customer()->create(['name' => 'Rahim Traders']);

    $viewer = userWithPermissions(['customers.view'], 'customer-viewer');
    $full = userWithPermissions([
        'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
    ], 'customer-manager');

    $readOnly = $this->actingAs($viewer)->get(route('customers.index'))->assertOk();
    $readOnly->assertSee('Rahim Traders');
    $readOnly->assertDontSee('Add Customer');
    $readOnly->assertDontSee('id="addContactModal"', false);
    $readOnly->assertDontSee('id="editContactModal"', false);
    $readOnly->assertDontSee(route('contact.delete', 1), false);

    $editable = $this->actingAs($full)->get(route('customers.index'))->assertOk();
    $editable->assertSee('Add Customer');
    $editable->assertSee('id="addContactModal"', false);
    $editable->assertSee('id="editContactModal"', false);
});

test('a role with create but not delete sees Add and no delete form', function () {
    CustomerGroup::create(['name' => 'Wholesale']);

    $user = userWithPermissions(['customer-groups.view', 'customer-groups.create'], 'group-adder');

    $response = $this->actingAs($user)->get(route('customer.groups'))->assertOk();

    $response->assertSee('Add Group');
    $response->assertDontSee(route('customer.group.delete', 1), false);
});

test('the payment accounts screen drops the actions the role lacks', function () {
    $type = AccountType::create(['name' => 'Bank']);
    PaymentAccount::create(['name' => 'City Bank', 'account_type_id' => $type->id, 'balance' => 100, 'is_active' => true]);

    // May look at the accounts and deposit, nothing else.
    $user = userWithPermissions(['accounts.view', 'accounts.deposit'], 'depositor');

    $response = $this->actingAs($user)->get(route('payment.accounts'))->assertOk();

    $response->assertSee('City Bank');
    $response->assertSee('id="depositModal"', false);
    $response->assertDontSee('id="fundTransferModal"', false);
    $response->assertDontSee('id="addAccountModal"', false);
    $response->assertDontSee('id="editAccountModal"', false);
    // The account-types tab belongs to its own permission.
    $response->assertDontSee('id="addAccountTypeModal"', false);
    $response->assertDontSee('Account Types');
});

test('the order list hides edit and delete but keeps the invoice for a read-only role', function () {
    $order = Order::factory()->create(['order_no' => 'ORD-0001']);

    $user = userWithPermissions(['orders.view', 'orders.invoice'], 'order-viewer');

    $response = $this->actingAs($user)->get(route('orders.index'))->assertOk();

    $response->assertSee(route('order.invoice', $order->id), false);
    $response->assertDontSee(route('order.edit', $order->id), false);
    // order.delete shares its URL with order.show, so look for the delete control itself.
    $response->assertDontSee('ri-delete-bin-line');
    $response->assertDontSee(route('orders.create'), false);
});

test('the sidebar only links to modules the role can open', function () {
    $user = userWithPermissions(['dashboard.view', 'orders.view', 'reports.cash-flow'], 'thin-role');

    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $response->assertSee(route('orders.index'), false);
    $response->assertSee(route('reports.cash-flow'), false);
    $response->assertDontSee(route('customers.index'), false);
    $response->assertDontSee(route('hs.codes'), false);
    $response->assertDontSee(route('payment.accounts'), false);
    $response->assertDontSee(route('reports.profit-loss'), false);
    $response->assertDontSee(route('roles.index'), false);
});

test('the warehouse portal hides the actions its role lacks', function () {
    $warehouse = Warehouse::factory()->create();

    // A stock-taker: sees inventory, cannot dispatch, cannot touch staff or expenses.
    $user = userWithPermissions(
        ['warehouse.dashboard.view', 'warehouse.inventory.view'],
        'warehouse',
        ['warehouse_id' => $warehouse->id],
    );

    $response = $this->actingAs($user)->get(route('warehouse.inventory.index'))->assertOk();

    $response->assertDontSee('id="dispatchModal"', false);
    $response->assertDontSee(route('warehouse.inventory.dispatch', 1), false);
    $response->assertDontSee(route('warehouse.staff.index'), false);
    $response->assertDontSee(route('warehouse.expenses.index'), false);

    $this->actingAs($user)->get(route('warehouse.staff.index'))->assertForbidden();
});

test('the HS code screen hides the importer from a role without hs.import', function () {
    $user = userWithPermissions(['hs.view', 'hs.create'], 'hs-editor');

    $response = $this->actingAs($user)->get(route('hs.codes'))->assertOk();

    $response->assertSee('Add HS Code');
    $response->assertDontSee('Import from Excel');
    $response->assertDontSee(route('hs.code.import'), false);
});
