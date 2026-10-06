<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // The permission catalog is defined once, in App\Support\PermissionCatalog.
        foreach (PermissionCatalog::all() as $group => $permissions) {
            foreach ($permissions as $key => [$name]) {
                Permission::updateOrCreate(['key' => $key], ['name' => $name, 'group' => $group]);
            }
        }

        // Anything no longer in the catalog (the old coarse "manage" keys, the product and
        // supplier permissions retired with those modules) is dropped.
        Permission::whereNotIn('key', PermissionCatalog::keys())->delete();

        // Roles (Admin, Customer & Warehouse are system roles that can't be deleted).
        $admin = Role::updateOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true, 'description' => 'Full access to everything']);
        $customer = Role::updateOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true, 'description' => 'Portal access only']);
        $warehouse = Role::updateOrCreate(['slug' => 'warehouse'], ['name' => 'Warehouse', 'is_system' => true, 'description' => 'Warehouse portal access only']);
        $staff = Role::updateOrCreate(['slug' => 'staff'], ['name' => 'Staff / Agent', 'description' => 'Sourcing, orders & logistics']);
        $accountant = Role::updateOrCreate(['slug' => 'accountant'], ['name' => 'Accountant', 'description' => 'Finance, costs & reports']);

        // Admin gets everything (also bypasses gates, but sync for a complete matrix).
        $admin->permissions()->sync(Permission::pluck('id'));

        // Customer: no admin-panel permissions (their portal gates access by role).
        $customer->permissions()->sync([]);

        // Warehouse: its own portal, and scanning cartons in and out.
        $this->grant($warehouse, array_merge(
            PermissionCatalog::group('Warehouse Portal'),
            ['orders.scan'],
        ));

        // Staff / Agent: operational modules, no finance.
        $this->grant($staff, array_merge(
            ['dashboard.view', 'warehouses.view', 'warehouses.enter'],
            PermissionCatalog::groups([
                'Customers', 'Customer Groups', 'HS Codes & Tariff', 'Quotations',
                'Transportation Modes', 'Packing Types', 'Orders',
                'Letters of Credit', 'Containers & Shipments',
            ]),
        ));

        // Accountant: finance, costs & reports — plus read-only access to orders so the
        // cost and payment panels on an order are reachable. Account balances are granted
        // deliberately, not by default.
        $this->grant($accountant, array_merge(
            ['dashboard.view', 'orders.view', 'orders.invoice', 'orders.payments.create'],
            array_diff(PermissionCatalog::group('Payment Accounts'), ['accounts.view-balance']),
            PermissionCatalog::groups([
                'Order Costs', 'Cost Categories', 'Account Types', 'Expenses',
                'Cost Types', 'Borrowing & Lending', 'Fixed Assets', 'Asset Depreciation',
                'Asset Categories', 'Warehouse Expense Categories', 'Reports',
            ]),
        ));

        // Existing users without a role become Admins (so nobody is locked out).
        User::whereNull('role_id')->update(['role_id' => $admin->id]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function grant(Role $role, array $keys): void
    {
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
    }
}
