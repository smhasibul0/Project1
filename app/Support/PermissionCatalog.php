<?php

namespace App\Support;

/**
 * The full list of permissions the application enforces, grouped by module.
 *
 * This is the single source of truth: the seeder and the migration build the
 * `permissions` table from it, and the Roles & Permissions screen renders it.
 * Every key here is an ability string — `can:orders.create` on a route and
 * `@can('orders.create')` in a view both resolve against it.
 */
class PermissionCatalog
{
    /**
     * Module => [permission key => [label, what it lets the role do]].
     *
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    public static function all(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => ['Open the dashboard', 'See the admin home screen and its summary tiles.'],
            ],

            'Users' => [
                'users.view' => ['View users', 'Open the user list and see who holds which role.'],
                'users.create' => ['Add users', 'Create a new admin, staff, warehouse or customer login.'],
                'users.edit' => ['Edit users', 'Change a user\'s details, role or password.'],
                'users.toggle' => ['Activate / deactivate users', 'Switch a login off without deleting it.'],
                'users.delete' => ['Delete users', 'Permanently remove a login.'],
            ],

            'Roles & Permissions' => [
                'roles.view' => ['View roles', 'Open this screen and see what each role grants.'],
                'roles.create' => ['Add roles', 'Create a new role.'],
                'roles.edit' => ['Edit roles & permissions', 'Rename a role and change what it can do.'],
                'roles.delete' => ['Delete roles', 'Remove a role that has no users left on it.'],
            ],

            'Customers' => [
                'customers.view' => ['View customers', 'Open the customer list and customer details.'],
                'customers.create' => ['Add customers', 'Create a customer record.'],
                'customers.edit' => ['Edit customers', 'Change details, pay terms and shipping marks.'],
                'customers.toggle' => ['Activate / deactivate customers', 'Switch a customer off without deleting them.'],
                'customers.delete' => ['Delete customers', 'Permanently remove a customer record.'],
                'customers.create-login' => ['Issue a portal login', 'Give a customer access to the customer portal.'],
            ],

            'Customer Groups' => [
                'customer-groups.view' => ['View customer groups', 'Open the customer group list.'],
                'customer-groups.create' => ['Add customer groups', 'Create a new group.'],
                'customer-groups.edit' => ['Edit customer groups', 'Rename a group or change its discount.'],
                'customer-groups.delete' => ['Delete customer groups', 'Remove a group.'],
            ],

            'HS Codes & Tariff' => [
                'hs.view' => ['View HS codes', 'Browse and search the customs tariff.'],
                'hs.create' => ['Add HS codes', 'Add a tariff line by hand.'],
                'hs.edit' => ['Edit HS codes', 'Change a tariff line\'s description or duty rates.'],
                'hs.delete' => ['Delete HS codes', 'Remove a tariff line.'],
                'hs.import' => ['Import the tariff book', 'Bulk-load HS codes and rates from a workbook.'],
            ],

            'Rates' => [
                'rates.view' => ['View rates', 'Open the Customs valuation reports and the reference price per HS code.'],
                'rates.upload' => ['Upload rates', 'Upload a Customs valuation report PDF.'],
                'rates.delete' => ['Delete rates', 'Remove an uploaded valuation report.'],
            ],

            'Exchange Rates' => [
                'exchange-rates.view' => ['View exchange rates', 'See the dollar rate for each day.'],
                'exchange-rates.create' => ['Add exchange rates', 'Set the dollar rate for a day that has none.'],
                'exchange-rates.edit' => ['Edit exchange rates', 'Change a day\'s dollar rate.'],
                'exchange-rates.delete' => ['Delete exchange rates', 'Remove a day\'s dollar rate.'],
            ],

            'Quotations' => [
                'quotations.view' => ['View quotations', 'Open the quotation list, customer requests and quote details.'],
                'quotations.create' => ['Create quotations', 'Build a new quote, including the packing-list import.'],
                'quotations.edit' => ['Edit quotations', 'Change a quote before it is accepted.'],
                'quotations.deny' => ['Deny quotation requests', 'Turn down a customer\'s request.'],
                'quotations.print' => ['Print quotations', 'Open the printable quotation.'],
                'quotations.delete' => ['Delete quotations', 'Remove a quotation.'],
            ],

            'Transportation Modes' => [
                'transportation-modes.view' => ['View transportation modes', 'Open the shipping-mode list.'],
                'transportation-modes.create' => ['Add transportation modes', 'Add a shipping mode.'],
                'transportation-modes.edit' => ['Edit transportation modes', 'Rename a shipping mode.'],
                'transportation-modes.delete' => ['Delete transportation modes', 'Remove a shipping mode.'],
            ],

            'Packing Types' => [
                'packing-types.view' => ['View packing types', 'Open the packing-type list.'],
                'packing-types.create' => ['Add packing types', 'Add a packing type.'],
                'packing-types.edit' => ['Edit packing types', 'Rename a packing type.'],
                'packing-types.delete' => ['Delete packing types', 'Remove a packing type.'],
            ],

            'Orders' => [
                'orders.view' => ['View orders', 'Open the order list and order details.'],
                'orders.create' => ['Create orders', 'Raise a new order, including from an accepted quotation.'],
                'orders.edit' => ['Edit orders', 'Change an order\'s items, rates and shipment details.'],
                'orders.delete' => ['Delete orders', 'Remove an order.'],
                'orders.update-status' => ['Update tracking status', 'Move an order along its timeline without editing it.'],
                'orders.payments.create' => ['Record customer payments', 'Take a payment against an order\'s dues.'],
                'orders.invoice' => ['View & print invoices', 'Open the printable invoice for an order.'],
                'orders.label' => ['Print carton labels', 'Open the QR carton labels for an order.'],
                'orders.scan' => ['Scan carton QR codes', 'Count cartons through each stage with the camera scanner.'],
            ],

            'Order Costs' => [
                'costs.view' => ['View order costs', 'See the cost breakdown and profit on an order.'],
                'costs.create' => ['Add order costs', 'Post a cost line against an order.'],
                'costs.delete' => ['Delete order costs', 'Remove a cost line from an order.'],
            ],

            'Cost Categories' => [
                'cost-categories.view' => ['View cost categories', 'Open the order-cost category list.'],
                'cost-categories.create' => ['Add cost categories', 'Add an order-cost category.'],
                'cost-categories.edit' => ['Edit cost categories', 'Rename an order-cost category.'],
                'cost-categories.delete' => ['Delete cost categories', 'Remove an order-cost category.'],
            ],

            'Letters of Credit' => [
                'lc.view' => ['View LCs', 'Open the LC list and LC details.'],
                'lc.create' => ['Open LCs', 'Open a letter of credit against an order.'],
                'lc.edit' => ['Edit LCs', 'Change an LC\'s terms and amounts.'],
                'lc.update-status' => ['Update LC status', 'Move an LC through its stages.'],
                'lc.delete' => ['Delete LCs', 'Remove an LC.'],
                'lc.costs.create' => ['Add LC charges', 'Post a bank or LC charge that feeds the order cost.'],
                'lc.costs.delete' => ['Delete LC charges', 'Remove an LC charge line.'],
                'lc.payments.create' => ['Pay LCs', 'Pay an LC in dollars from a bank account, in one go or in parts.'],
                'lc.payments.delete' => ['Reverse LC payments', 'Undo an LC payment and return its taka to the account.'],
            ],

            'Containers & Shipments' => [
                'containers.view' => ['View containers', 'Open the container list and container details.'],
                'containers.create' => ['Create containers', 'Open a new container or shipment.'],
                'containers.edit' => ['Edit containers', 'Change a container\'s details.'],
                'containers.update-status' => ['Update container status', 'Move a container through its stages.'],
                'containers.delete' => ['Delete containers', 'Remove a container.'],
                'containers.orders.assign' => ['Assign orders to a container', 'Load an order into a container.'],
                'containers.orders.remove' => ['Remove orders from a container', 'Take an order back out of a container.'],
                'containers.costs.create' => ['Add container costs', 'Post a shared cost that is distributed to member orders.'],
                'containers.costs.delete' => ['Delete container costs', 'Remove a shared container cost.'],
                'containers.documents.upload' => ['Upload container documents', 'Attach a B/L, packing list or invoice.'],
                'containers.documents.delete' => ['Delete container documents', 'Remove an attached document.'],
                'containers.lists.print' => ['Print packing & loading lists', 'Open the generated container lists.'],
            ],

            'Payment Accounts' => [
                'accounts.view' => ['View payment accounts', 'Open the account list and each account book.'],
                'accounts.view-balance' => ['See account balances', 'Show what an account holds wherever an account is listed. Leave this off for staff who only need to pick an account to pay from.'],
                'accounts.create' => ['Add payment accounts', 'Create a cash or bank account.'],
                'accounts.edit' => ['Edit payment accounts', 'Change an account\'s name and details.'],
                'accounts.toggle' => ['Activate / deactivate accounts', 'Switch an account off without deleting it.'],
                'accounts.deposit' => ['Record deposits', 'Pay money into an account.'],
                'accounts.fund-transfer' => ['Transfer between accounts', 'Move money from one account to another.'],
                'accounts.sell-dollars' => ['Sell dollars', 'Convert dollars an account holds into taka.'],
                'accounts.transactions.edit' => ['Edit ledger entries', 'Correct a deposit or fund transfer after the fact.'],
                'accounts.transactions.delete' => ['Delete ledger entries', 'Reverse a deposit or fund transfer.'],
            ],

            'Account Types' => [
                'account-types.view' => ['View account types', 'See the cash/bank account types.'],
                'account-types.create' => ['Add account types', 'Add an account type.'],
                'account-types.edit' => ['Edit account types', 'Rename an account type.'],
                'account-types.delete' => ['Delete account types', 'Remove an account type.'],
            ],

            'Expenses' => [
                'office.expenses.view' => ['View expenses', 'Open the monthly and regular expense lists.'],
                'office.expenses.create' => ['Add expenses', 'Post a monthly or regular expense.'],
                'office.expenses.edit' => ['Edit expenses', 'Change an expense.'],
                'office.expenses.generate' => ['Generate monthly expenses', 'Raise the month\'s fixed costs in one go.'],
                'office.expenses.delete' => ['Delete expenses', 'Remove an expense.'],
                'office.expenses.payments.create' => ['Settle expenses', 'Pay an expense from a payment account.'],
                'office.expenses.payments.delete' => ['Reverse expense payments', 'Undo a payment and put the money back.'],
            ],

            'Cost Types' => [
                'office.cost-types.view' => ['View cost types', 'Open the fixed/variable cost-type list.'],
                'office.cost-types.create' => ['Add cost types', 'Add a cost type.'],
                'office.cost-types.edit' => ['Edit cost types', 'Change a cost type\'s name or nature.'],
                'office.cost-types.delete' => ['Delete cost types', 'Remove a cost type.'],
            ],

            'Borrowing & Lending' => [
                'loans.view' => ['View money borrowed & lent', 'Open the borrowing and lending registers.'],
                'loans.create' => ['Record borrowing & lending', 'Log money taken in or handed out.'],
                'loans.edit' => ['Edit loans', 'Change a loan\'s terms.'],
                'loans.close' => ['Settle & write off loans', 'Close a loan or write it off.'],
                'loans.delete' => ['Delete loans', 'Remove a loan record.'],
                'loans.payments.create' => ['Record loan payments', 'Log a repayment in either direction.'],
                'loans.payments.delete' => ['Delete loan payments', 'Reverse a repayment.'],
            ],

            'Fixed Assets' => [
                'assets.view' => ['View assets', 'Open the fixed-asset register.'],
                'assets.create' => ['Add assets', 'Put an asset on the register.'],
                'assets.edit' => ['Edit assets', 'Change an asset\'s cost, life or category.'],
                'assets.dispose' => ['Dispose of assets', 'Mark an asset as sold, scrapped or written off.'],
                'assets.restore' => ['Restore disposed assets', 'Put a disposed asset back on the register.'],
                'assets.delete' => ['Delete assets', 'Remove an asset record.'],
            ],

            'Asset Depreciation' => [
                'assets.depreciation.view' => ['View depreciation', 'Open the monthly depreciation schedule.'],
                'assets.depreciation.generate' => ['Post depreciation', 'Run a month\'s depreciation.'],
                'assets.depreciation.delete' => ['Reverse depreciation', 'Undo a posted depreciation entry or month.'],
            ],

            'Asset Categories' => [
                'asset-categories.view' => ['View asset categories', 'Open the asset category list.'],
                'asset-categories.create' => ['Add asset categories', 'Add a category with its depreciation defaults.'],
                'asset-categories.edit' => ['Edit asset categories', 'Change a category\'s depreciation defaults.'],
                'asset-categories.delete' => ['Delete asset categories', 'Remove a category.'],
            ],

            'Warehouses' => [
                'warehouses.view' => ['View warehouses', 'Open the warehouse list.'],
                'warehouses.create' => ['Add warehouses', 'Create a warehouse.'],
                'warehouses.edit' => ['Edit warehouses', 'Change a warehouse\'s details.'],
                'warehouses.delete' => ['Delete warehouses', 'Remove a warehouse.'],
                'warehouses.enter' => ['Open a warehouse portal', 'Step into a warehouse\'s own screens from the admin panel.'],
            ],

            'Warehouse Expense Categories' => [
                'expense-categories.view' => ['View expense categories', 'Open the warehouse expense category list.'],
                'expense-categories.create' => ['Add expense categories', 'Add a category or sub-category.'],
                'expense-categories.edit' => ['Edit expense categories', 'Rename a category.'],
                'expense-categories.delete' => ['Delete expense categories', 'Remove a category.'],
            ],

            'Warehouse Portal' => [
                'warehouse.dashboard.view' => ['Open the warehouse dashboard', 'See the warehouse home screen.'],
                'warehouse.inventory.view' => ['View warehouse stock', 'See what is on hand and its movements.'],
                'warehouse.inventory.dispatch' => ['Dispatch stock', 'Send stock out of the warehouse.'],
                'warehouse.orders.view' => ['View warehouse orders', 'See the orders routed to this warehouse.'],
                'warehouse.expenses.view' => ['View warehouse expenses', 'Open the warehouse expense list.'],
                'warehouse.expenses.create' => ['Add warehouse expenses', 'Post an expense for the warehouse.'],
                'warehouse.expenses.edit' => ['Edit warehouse expenses', 'Change a warehouse expense.'],
                'warehouse.expenses.delete' => ['Delete warehouse expenses', 'Remove a warehouse expense.'],
                'warehouse.expenses.payments.create' => ['Settle warehouse expenses', 'Pay an expense from a payment account.'],
                'warehouse.expenses.payments.delete' => ['Reverse warehouse expense payments', 'Undo a payment.'],
                'warehouse.payroll.view' => ['View payroll', 'See the warehouse salary run.'],
                'warehouse.staff.view' => ['View warehouse staff', 'Open the staff list and staff details.'],
                'warehouse.staff.create' => ['Add warehouse staff', 'Add a staff member.'],
                'warehouse.staff.edit' => ['Edit warehouse staff', 'Change a staff member\'s details or salary.'],
                'warehouse.staff.delete' => ['Delete warehouse staff', 'Remove a staff member.'],
                'warehouse.staff.documents.upload' => ['Upload staff documents', 'Attach a document to a staff member.'],
                'warehouse.staff.documents.delete' => ['Delete staff documents', 'Remove an attached document.'],
                'warehouse.staff.salary.create' => ['Pay salaries', 'Record a salary payment from a payment account.'],
                'warehouse.staff.salary.delete' => ['Reverse salary payments', 'Undo a salary payment.'],
            ],

            'Reports' => [
                'reports.profit-loss' => ['Profit & Loss', 'Revenue, costs and margin across orders.'],
                'reports.receivables' => ['Receivables', 'Outstanding customer dues and their aging.'],
                'reports.balance-sheet' => ['Balance Sheet', 'Cash, bank, receivables, lending and borrowing.'],
                'reports.cash-flow' => ['Cash Flow', 'The money-in / money-out ledger across every account.'],
                'reports.warehouse-summary' => ['Warehouse Operations', 'Stock, staff and expenses per warehouse.'],
            ],

            'Settings' => [
                'settings.view' => ['View company settings', 'See the company and invoice settings.'],
                'settings.edit' => ['Edit company settings', 'Change the company details shown on invoices and quotes.'],
            ],

            'Activity Log' => [
                'activity.view' => ['View activity log', 'See who added, edited, updated or deleted every record, and what they changed.'],
            ],
        ];
    }

    /**
     * Every permission key in the catalog.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::all() as $permissions) {
            foreach ($permissions as $key => $_) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Every key belonging to one module group.
     *
     * @return array<int, string>
     */
    public static function group(string $group): array
    {
        return array_keys(self::all()[$group] ?? []);
    }

    /**
     * Every key belonging to any of the given module groups.
     *
     * @param  array<int, string>  $groups
     * @return array<int, string>
     */
    public static function groups(array $groups): array
    {
        return array_merge(...array_map(fn (string $group) => self::group($group), $groups));
    }

    /**
     * The coarse "manage the whole module" permissions this catalog replaced, mapped to the
     * granular keys that reproduce the access each one used to grant. Used once by the
     * migration so existing roles keep working, and kept afterwards as the record of what
     * the old keys meant.
     *
     * @return array<string, array<int, string>>
     */
    public static function legacyMap(): array
    {
        return [
            'users.manage' => self::group('Users'),
            'roles.manage' => self::group('Roles & Permissions'),
            'customers.manage' => self::groups(['Customers', 'Customer Groups']),
            'hs.manage' => self::group('HS Codes & Tariff'),
            'quotations.manage' => self::groups(['Quotations', 'Transportation Modes', 'Packing Types']),
            // Order costs were reachable from the order page, so cost managers could open orders.
            'costs.manage' => array_merge(['orders.view'], self::groups(['Order Costs', 'Cost Categories'])),
            'lc.manage' => self::group('Letters of Credit'),
            'containers.manage' => self::group('Containers & Shipments'),
            // Balances stay admin-only unless they are granted deliberately.
            'accounts.manage' => array_merge(
                array_diff(self::group('Payment Accounts'), ['accounts.view-balance']),
                self::group('Account Types'),
            ),
            'payments.manage' => ['orders.payments.create'],
            'reports.view' => self::group('Reports'),
            'office.expenses.manage' => self::groups(['Expenses', 'Cost Types']),
            'loans.manage' => self::group('Borrowing & Lending'),
            'assets.manage' => self::groups(['Fixed Assets', 'Asset Depreciation', 'Asset Categories']),
            'warehouses.manage' => self::group('Warehouses'),
            'expenses.manage' => self::group('Warehouse Expense Categories'),
            'settings.manage' => self::group('Settings'),
            'orders.manage' => array_diff(self::group('Orders'), ['orders.scan']),
            'orders.update-status' => ['orders.view', 'orders.update-status'],
            'orders.scan' => ['orders.scan'],
        ];
    }
}
