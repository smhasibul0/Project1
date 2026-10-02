<?php

use App\Models\AccountType;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Container;
use App\Models\CustomerGroup;
use App\Models\HsCode;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentAccount;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    $this->admin = adminUser(['first_name' => 'Hasib', 'last_name' => 'Rahman']);
    $this->customer = Contact::factory()->customer()->create();
});

/**
 * Place an order through the form, as the admin.
 */
function activityTestOrder(object $test, array $overrides = []): Order
{
    $test->actingAs($test->admin)->post(route('order.store'), array_merge([
        'customer_id' => $test->customer->id,
        'sell_rate_per_cbm' => 100,
        'items' => [['item_description' => 'Toys', 'quantity' => 2, 'cbm' => 2, 'declared_value' => 60]],
    ], $overrides));

    return Order::latest('id')->firstOrFail();
}

/**
 * @return Builder<ActivityLog>
 */
function entriesFor(object $record, ?string $action = null)
{
    return ActivityLog::where('subject_type', $record->getMorphClass())
        ->where('subject_id', $record->getKey())
        ->when($action, fn ($q) => $q->where('action', $action));
}

test('adding, editing and deleting a record is logged with who did it', function () {
    $this->actingAs($this->admin)->post(route('customer.group.store'), ['name' => 'Wholesale', 'discount_type' => 'fixed']);
    $group = CustomerGroup::where('name', 'Wholesale')->firstOrFail();

    $this->actingAs($this->admin)->put(route('customer.group.update', $group->id), ['name' => 'Wholesale Buyers', 'discount_type' => 'fixed']);
    $this->actingAs($this->admin)->delete(route('customer.group.delete', $group->id));

    $added = entriesFor($group, ActivityLog::ADDED)->sole();
    expect($added->user_id)->toBe($this->admin->id)
        ->and($added->userLabel())->toBe('Hasib Rahman')
        ->and($added->subject_label)->toBe('Wholesale');

    $edited = entriesFor($group, ActivityLog::EDITED)->sole();
    expect($edited->field_changes)->toBe(['name' => ['old' => 'Wholesale', 'new' => 'Wholesale Buyers']]);

    // The record is gone, but its entry still says what it was.
    $deleted = entriesFor($group, ActivityLog::DELETED)->sole();
    expect($deleted->subject_label)->toBe('Wholesale Buyers')
        ->and(CustomerGroup::find($group->id))->toBeNull();
});

test('a status change is logged as a status update, in words', function () {
    $order = activityTestOrder($this);

    $this->actingAs($this->admin)->post(route('order.status', $order->id), ['goods_status' => 'shipped']);

    $entry = entriesFor($order, ActivityLog::STATUS_UPDATED)->sole();
    expect($entry->field_changes['goods_status'])->toBe(['old' => 'Pending', 'new' => 'Shipped'])
        ->and($entry->actionLabel())->toBe('Status updated')
        ->and($entry->userLabel())->toBe('Hasib Rahman');
});

test('a payment is logged under its order, and totals the app works out are not logged as edits', function () {
    $order = activityTestOrder($this);
    $account = PaymentAccount::create(['name' => 'City Bank', 'account_type_id' => AccountType::create(['name' => 'Bank'])->id, 'balance' => 1000, 'is_active' => true]);

    $this->actingAs($this->admin)->post(route('order.payment', $order->id), [
        'amount' => 150,
        'payment_date' => '2026-10-02',
        'method' => 'Cash',
        'payment_account_id' => $account->id,
    ])->assertSessionHas('success');

    $payment = OrderPayment::sole();
    $entry = entriesFor($payment, ActivityLog::ADDED)->sole();
    expect($entry->parent_id)->toBe($order->id)
        ->and($entry->subject_label)->toContain('৳150.00')
        ->and(ActivityLog::forRecord($order)->where('subject_type', $payment->getMorphClass())->exists())->toBeTrue();

    // Received, due and the account balance all moved — none of that is somebody's edit.
    expect(entriesFor($order, ActivityLog::EDITED)->exists())->toBeFalse()
        ->and(entriesFor($account)->where('action', '!=', ActivityLog::ADDED)->exists())->toBeFalse();
});

test('editing an order logs one entry and does not log its payments as added again', function () {
    $order = activityTestOrder($this, ['payments' => [['amount' => 50, 'payment_date' => '2026-10-01', 'method' => 'Cash']]]);

    $this->actingAs($this->admin)->put(route('order.update', $order->id), [
        'customer_id' => $this->customer->id,
        'sell_rate_per_cbm' => 300,
        'payments' => [['amount' => 50, 'payment_date' => '2026-10-01', 'method' => 'Cash']],
        'items' => [['item_description' => 'Toys', 'quantity' => 3, 'cbm' => 3, 'declared_value' => 90]],
    ]);

    $edit = entriesFor($order, ActivityLog::EDITED)->sole();
    expect($edit->description)->toBe('Changed the items')
        ->and((float) $edit->field_changes['sell_rate_per_cbm']['old'])->toBe(100.0)
        ->and((float) $edit->field_changes['sell_rate_per_cbm']['new'])->toBe(300.0);

    expect(ActivityLog::where('subject_type', (new OrderPayment)->getMorphClass())->where('action', ActivityLog::ADDED)->count())->toBe(1);
});

test('one quotation save is one entry, however many times it writes the quotation', function () {
    $this->actingAs($this->admin)->post(route('quotation.store'), [
        'customer_id' => $this->customer->id,
        'packing_list' => fakePackingList(),
        'sell_rate_per_cbm' => 100,
        'items' => [['package_quantity' => 2, 'cbm' => 2, 'declared_value' => 60]],
    ]);
    $quotation = Quotation::firstOrFail();

    $this->actingAs($this->admin)->put(route('quotation.update', $quotation->id), [
        'customer_id' => $this->customer->id,
        'action' => 'send',
        'sell_rate_per_cbm' => 200,
        'items' => [['package_quantity' => 3, 'cbm' => 3, 'declared_value' => 150]],
    ]);

    $entries = entriesFor($quotation)->where('action', '!=', ActivityLog::ADDED)->get();
    expect($entries)->toHaveCount(1);

    // Sent to the customer (a status update), with the new rate and items folded in.
    expect($entries[0]->action)->toBe(ActivityLog::STATUS_UPDATED)
        ->and(array_keys($entries[0]->field_changes))->toContain('status', 'sell_rate_per_cbm')
        ->and($entries[0]->description)->toBe('Changed the items');
});

test('a password change is logged without the password', function () {
    $this->actingAs($this->admin)->post(route('admin.password.update'), [
        'old_password' => 'password',
        'new_password' => 'a-new-password',
        'new_password_confirmation' => 'a-new-password',
    ]);

    $entry = entriesFor($this->admin, ActivityLog::EDITED)->sole();
    expect($entry->field_changes)->toBe(['password' => ['hidden' => true]])
        ->and($entry->changeLines()[0]['field'])->toBe('Password');
});

test('a role names the permissions granted and taken away', function () {
    $role = Role::create(['name' => 'Clerk', 'slug' => 'clerk']);
    $role->permissions()->sync(Permission::where('key', 'customers.view')->pluck('id'));

    $this->actingAs($this->admin)->put(route('role.update', $role->id), [
        'name' => 'Clerk',
        'permissions' => Permission::where('key', 'orders.view')->pluck('id')->all(),
    ]);

    $entry = entriesFor($role, ActivityLog::EDITED)->sole();
    expect($entry->field_changes['permissions_granted']['new'])->toBe(Permission::where('key', 'orders.view')->value('name'))
        ->and($entry->field_changes['permissions_removed']['old'])->toBe(Permission::where('key', 'customers.view')->value('name'));
});

test('loading an order into a container shows in both histories', function () {
    $order = activityTestOrder($this);
    $container = Container::factory()->create();

    $this->actingAs($this->admin)->post(route('container.order.store', $container->id), ['order_id' => $order->id, 'ctn' => 4]);

    $entry = ActivityLog::forRecord($container)->where('description', 'like', 'Loaded into container%')->sole();
    expect($entry->subject_id)->toBe($order->id)
        ->and(ActivityLog::forRecord($order)->whereKey($entry->id)->exists())->toBeTrue();
});

test('a tariff import is one entry, not one per code', function () {
    $this->actingAs($this->admin)->post(route('hs.code.import'), ['sheet' => fakeTariffSheet()]);

    expect(HsCode::count())->toBe(5)
        ->and(ActivityLog::where('action', ActivityLog::IMPORTED)->sole()->description)->toContain('5 new')
        ->and(ActivityLog::where('subject_type', (new HsCode)->getMorphClass())->where('action', ActivityLog::ADDED)->exists())->toBeFalse();
});

test('the activity log lists what happened, by whom', function () {
    $order = activityTestOrder($this);
    $this->actingAs($this->admin)->post(route('order.status', $order->id), ['goods_status' => 'shipped']);

    $this->actingAs($this->admin)->get(route('activity.index'))
        ->assertOk()
        ->assertSee('Status updated')
        ->assertSee('Hasib Rahman')
        ->assertSee($order->order_no)
        ->assertSee('Shipped');

    $this->actingAs($this->admin)->get(route('activity.index', ['action' => ActivityLog::ADDED, 'type' => 'order']))
        ->assertOk()
        ->assertSee($order->order_no)
        ->assertDontSee('Shipped');
});

test("a record's history stays readable after it is deleted", function () {
    $order = activityTestOrder($this);
    $this->actingAs($this->admin)->delete(route('order.delete', $order->id));

    $this->actingAs($this->admin)->get(route('activity.index', ['type' => 'order', 'id' => $order->id]))
        ->assertOk()
        ->assertSee('History — Order '.$order->order_no)
        ->assertSee('Deleted');
});

test('a record page shows who added and last changed it', function () {
    $order = activityTestOrder($this);
    $this->actingAs($this->admin)->post(route('order.status', $order->id), ['goods_status' => 'sourcing']);

    $this->actingAs($this->admin)->get(route('order.show', $order->id))
        ->assertOk()
        ->assertSee('View full history')
        ->assertSeeInOrder(['Added', 'by', 'Hasib Rahman'])
        ->assertSee('Last status updated');
});

test('list rows link to each record\'s history', function () {
    $this->actingAs($this->admin)->get(route('customers.index'))
        ->assertOk()
        ->assertSee(route('activity.index', ['type' => 'customer', 'id' => $this->customer->id]));
});

test('only users allowed to see the activity log see it', function () {
    $order = activityTestOrder($this);
    $clerk = userWithPermissions(['dashboard.view', 'orders.view']);

    $this->actingAs($clerk)->get(route('activity.index'))->assertForbidden();

    $this->actingAs($clerk)->get(route('order.show', $order->id))
        ->assertOk()
        ->assertDontSee('View full history');

    $this->actingAs(userWithPermissions(['dashboard.view', 'activity.view'], 'auditor'))
        ->get(route('activity.index'))
        ->assertOk();
});

test('work done without anyone signed in is put down to the system', function () {
    $group = CustomerGroup::create(['name' => 'Seeded', 'discount_type' => 'fixed']);

    expect(entriesFor($group, ActivityLog::ADDED)->sole()->userLabel())->toBe('System');
});

test('the new permission is written by the migration and held by the admin role', function () {
    expect(Permission::where('key', 'activity.view')->exists())->toBeTrue();
    expect(User::find($this->admin->id)->can('activity.view'))->toBeTrue();
});
