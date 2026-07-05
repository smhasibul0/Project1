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
            'Contacts' => [
                'contacts.manage' => 'Manage suppliers & customers',
            ],
            'Products' => [
                'products.manage' => 'Manage products & inventory',
            ],
            'Quotations' => [
                'quotations.manage' => 'Manage quotation requests & quotes',
            ],
            'Orders' => [
                'orders.manage' => 'Manage orders & tracking',
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
        ];

        foreach ($catalog as $group => $permissions) {
            foreach ($permissions as $key => $name) {
                Permission::updateOrCreate(['key' => $key], ['name' => $name, 'group' => $group]);
            }
        }

        // Roles (Admin & Customer are system roles that can't be deleted).
        $admin = Role::updateOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true, 'description' => 'Full access to everything']);
        $customer = Role::updateOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true, 'description' => 'Portal access only']);
        $staff = Role::updateOrCreate(['slug' => 'staff'], ['name' => 'Staff / Agent', 'description' => 'Sourcing, orders & logistics']);
        $accountant = Role::updateOrCreate(['slug' => 'accountant'], ['name' => 'Accountant', 'description' => 'Finance, costs & reports']);

        // Admin gets everything (also bypasses gates, but sync for a complete matrix).
        $admin->permissions()->sync(Permission::pluck('id'));

        // Customer: no admin-panel permissions.
        $customer->permissions()->sync([]);

        // Staff / Agent: operational modules, no finance.
        $staff->permissions()->sync(
            Permission::whereIn('key', [
                'contacts.manage', 'products.manage', 'quotations.manage',
                'orders.manage', 'lc.manage', 'containers.manage',
            ])->pluck('id')
        );

        // Accountant: finance & costs.
        $accountant->permissions()->sync(
            Permission::whereIn('key', [
                'costs.manage', 'accounts.manage', 'payments.manage', 'reports.view',
            ])->pluck('id')
        );

        // Existing users without a role become Admins (so nobody is locked out).
        User::whereNull('role_id')->update(['role_id' => $admin->id]);
    }
}
