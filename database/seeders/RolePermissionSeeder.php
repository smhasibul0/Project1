<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permission catalog, grouped by module (one "manage" permission per module).
        $catalog = [
            'Access Control' => [
                'users.manage' => 'Manage users',
                'roles.manage' => 'Manage roles & permissions',
            ],
            'Customers' => [
                'customers.manage' => 'Manage customers & customer groups',
            ],
            'HS Codes' => [
                'hs.manage' => 'Manage HS codes & the customs tariff',
            ],
            'Quotations' => [
                'quotations.manage' => 'Manage quotation requests & quotes',
            ],
            'Orders' => [
                'orders.manage' => 'Manage orders & tracking',
                'orders.update-status' => 'Update order tracking status only',
            ],
            'Order Costs' => [
                'costs.manage' => 'Manage order costs',
            ],
            'LC' => [
                'lc.manage' => 'Manage letters of credit',
            ],
            'Containers' => [
                'containers.manage' => 'Manage containers & shipments',
            ],
            'Finance' => [
                'accounts.manage' => 'Manage payment accounts',
                'payments.manage' => 'Manage payments',
                'reports.view' => 'View reports',
            ],
            'Warehouse' => [
                'warehouses.manage' => 'Manage warehouses',
                'expenses.manage' => 'Manage warehouse expense categories',
            ],
            'Settings' => [
                'settings.manage' => 'Manage company & invoice settings',
            ],
        ];

        foreach ($catalog as $group => $permissions) {
            foreach ($permissions as $key => $name) {
                Permission::updateOrCreate(['key' => $key], ['name' => $name, 'group' => $group]);
            }
        }

        // Retired with the product catalogue and the supplier directory — the
        // business only ships other people's goods.
        Permission::whereIn('key', ['products.manage', 'contacts.manage'])->delete();

        // Roles (Admin & Customer are system roles that can't be deleted).
        $admin = Role::updateOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true, 'description' => 'Full access to everything']);
        $customer = Role::updateOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true, 'description' => 'Portal access only']);
        $warehouse = Role::updateOrCreate(['slug' => 'warehouse'], ['name' => 'Warehouse', 'is_system' => true, 'description' => 'Warehouse portal access only']);
        $staff = Role::updateOrCreate(['slug' => 'staff'], ['name' => 'Staff / Agent', 'description' => 'Sourcing, orders & logistics']);
        $accountant = Role::updateOrCreate(['slug' => 'accountant'], ['name' => 'Accountant', 'description' => 'Finance, costs & reports']);

        // Admin gets everything (also bypasses gates, but sync for a complete matrix).
        $admin->permissions()->sync(Permission::pluck('id'));

        // Customer & Warehouse: no admin-panel permissions (their own portals gate access by role).
        $customer->permissions()->sync([]);
        $warehouse->permissions()->sync([]);

        // Staff / Agent: operational modules, no finance.
        $staff->permissions()->sync(
            Permission::whereIn('key', [
                'customers.manage', 'hs.manage', 'warehouses.manage', 'quotations.manage',
                'orders.manage', 'orders.update-status', 'lc.manage', 'containers.manage',
            ])->pluck('id')
        );

        // Accountant: finance & costs.
        $accountant->permissions()->sync(
            Permission::whereIn('key', [
                'costs.manage', 'accounts.manage', 'payments.manage', 'reports.view', 'expenses.manage',
            ])->pluck('id')
        );

        // Existing users without a role become Admins (so nobody is locked out).
        User::whereNull('role_id')->update(['role_id' => $admin->id]);
    }
}
