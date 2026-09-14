<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function userWithRole(string $slug): User
{
    return User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
}

test('an admin can reach any permission-gated admin page', function () {
    $this->actingAs(userWithRole('admin'))->get(route('customers.index'))->assertOk();
    $this->actingAs(userWithRole('admin'))->get(route('users.index'))->assertOk();
    $this->actingAs(userWithRole('admin'))->get(route('payment.accounts'))->assertOk();
});

test('a role only reaches modules its permissions allow', function () {
    // Accountant has finance permissions but not contacts.
    $this->actingAs(userWithRole('accountant'))->get(route('payment.accounts'))->assertOk();
    $this->actingAs(userWithRole('accountant'))->get(route('customers.index'))->assertForbidden();

    // Staff has operational permissions but not user management.
    $this->actingAs(userWithRole('staff'))->get(route('customers.index'))->assertOk();
    $this->actingAs(userWithRole('staff'))->get(route('users.index'))->assertForbidden();
});

test('a customer is bounced from the admin panel to their portal', function () {
    $customer = userWithRole('customer');

    $this->actingAs($customer)->get(route('customers.index'))->assertRedirect(route('portal.dashboard'));
    $this->actingAs($customer)->get(route('hs.codes'))->assertRedirect(route('portal.dashboard'));
});

test('hasPermission reflects the assigned role, admin bypasses', function () {
    expect(userWithRole('admin')->hasPermission('anything.at.all'))->toBeTrue();
    expect(userWithRole('accountant')->hasPermission('accounts.view'))->toBeTrue();
    expect(userWithRole('accountant')->hasPermission('customers.view'))->toBeFalse();
});

test('admin can create a user and assign a role', function () {
    $admin = userWithRole('admin');
    $staffRole = Role::where('slug', 'staff')->first();

    $this->actingAs($admin)->post(route('user.store'), [
        'first_name' => 'New',
        'last_name' => 'Agent',
        'username' => 'newagent',
        'email' => 'agent@example.com',
        'role_id' => $staffRole->id,
        'password' => 'password123',
        'is_active' => '1',
    ])->assertRedirect()->assertSessionHas('success');

    $user = User::where('username', 'newagent')->firstOrFail();
    expect($user->role_id)->toBe($staffRole->id);
    expect($user->status)->toBe('active');
});

test('admin can change what a role can do', function () {
    $admin = userWithRole('admin');
    $staff = Role::where('slug', 'staff')->firstOrFail();
    $permIds = Permission::whereIn('key', ['customers.view', 'orders.view'])->pluck('id')->all();

    $this->actingAs($admin)->put(route('role.update', $staff->id), [
        'name' => 'Staff / Agent',
        'permissions' => $permIds,
    ])->assertRedirect();

    expect($staff->fresh()->permissions->pluck('key')->sort()->values()->all())
        ->toBe(['customers.view', 'orders.view']);
});

test('the admin role permissions cannot be narrowed', function () {
    $admin = userWithRole('admin');
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $before = $adminRole->permissions()->count();

    $this->actingAs($admin)->put(route('role.update', $adminRole->id), [
        'name' => 'Admin',
        'permissions' => [], // attempt to strip everything
    ]);

    expect($adminRole->fresh()->permissions()->count())->toBe($before);
});

test('system roles cannot be deleted', function () {
    $admin = userWithRole('admin');
    $customerRole = Role::where('slug', 'customer')->firstOrFail();

    $this->actingAs($admin)->delete(route('role.delete', $customerRole->id))
        ->assertSessionHas('error');

    expect(Role::where('slug', 'customer')->exists())->toBeTrue();
});
