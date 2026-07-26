<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Backend\AccountTypeController;
use App\Http\Controllers\Backend\BrandController;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\CompanySettingController;
use App\Http\Controllers\Backend\ContactController;
use App\Http\Controllers\Backend\ContainerController;
use App\Http\Controllers\Backend\CostCategoryController;
use App\Http\Controllers\Backend\CustomerGroupController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\ExpenseCategoryController;
use App\Http\Controllers\Backend\LcController;
use App\Http\Controllers\Backend\OrderController;
use App\Http\Controllers\Backend\PackingTypeController;
use App\Http\Controllers\Backend\PaymentAccountController;
use App\Http\Controllers\Backend\ProductController;
use App\Http\Controllers\Backend\QuotationController;
use App\Http\Controllers\Backend\ReportController;
use App\Http\Controllers\Backend\RoleController;
use App\Http\Controllers\Backend\TransportationModeController;
use App\Http\Controllers\Backend\UnitController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WarehouseController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Http\Controllers\Warehouse\ExpenseController as WarehouseExpenseController;
use App\Http\Controllers\Warehouse\InventoryController as WarehouseInventoryController;
use App\Http\Controllers\Warehouse\OrderController as WarehouseOrderController;
use App\Http\Controllers\Warehouse\StaffController as WarehouseStaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public order tracking (no login required)
Route::get('/track', [TrackController::class, 'index'])->name('order.track');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'admin', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Customer Portal (customer logins only; every query scoped to their linked contact)
Route::middleware(['auth', 'customer'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');

    Route::get('/quotations', [PortalController::class, 'quotations'])->name('quotations');
    Route::get('/quotations/new', [PortalController::class, 'quotationCreate'])->name('quotation.create');
    Route::post('/quotations', [PortalController::class, 'quotationStore'])->name('quotation.store');
    Route::get('/quotations/{id}', [PortalController::class, 'quotationShow'])->name('quotation.show');
    Route::post('/quotations/{id}/accept', [PortalController::class, 'quotationAccept'])->name('quotation.accept');
    Route::post('/quotations/{id}/negotiate', [PortalController::class, 'quotationNegotiate'])->name('quotation.negotiate');

    Route::get('/orders', [PortalController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}', [PortalController::class, 'orderShow'])->name('order.show');

    Route::get('/payments', [PortalController::class, 'payments'])->name('payments');
});

/*
|--------------------------------------------------------------------------
| Warehouse portal (one login per warehouse, scoped to its own data)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'warehouse'])->prefix('warehouse')->name('warehouse.')->group(function () {
    Route::get('/', [WarehouseDashboardController::class, 'index'])->name('dashboard');

    Route::get('/inventory', [WarehouseInventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{id}', [WarehouseInventoryController::class, 'show'])->name('inventory.show');
    Route::post('/inventory/{id}/dispatch', [WarehouseInventoryController::class, 'dispatch'])->name('inventory.dispatch');

    Route::get('/orders', [WarehouseOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [WarehouseOrderController::class, 'show'])->name('orders.show');

    Route::get('/expenses', [WarehouseExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [WarehouseExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{id}', [WarehouseExpenseController::class, 'destroy'])->name('expenses.delete');
    Route::post('/expenses/{id}/payments', [WarehouseExpenseController::class, 'storePayment'])->name('expenses.payments.store');
    Route::delete('/expenses/{id}/payments/{paymentId}', [WarehouseExpenseController::class, 'destroyPayment'])->name('expenses.payments.delete');

    Route::get('/staff', [WarehouseStaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [WarehouseStaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{id}', [WarehouseStaffController::class, 'show'])->name('staff.show');
    Route::put('/staff/{id}', [WarehouseStaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{id}', [WarehouseStaffController::class, 'destroy'])->name('staff.delete');
    Route::post('/staff/{id}/documents', [WarehouseStaffController::class, 'storeDocument'])->name('staff.document.store');
    Route::delete('/staff/{id}/documents/{documentId}', [WarehouseStaffController::class, 'destroyDocument'])->name('staff.document.delete');
    Route::post('/staff/{id}/salary', [WarehouseStaffController::class, 'storeSalary'])->name('staff.salary.store');
    Route::delete('/staff/{id}/salary/{paymentId}', [WarehouseStaffController::class, 'destroySalary'])->name('staff.salary.delete');
});

require __DIR__.'/auth.php';

Route::get('/admin/logout', [AdminController::class, 'AdminLogout'])->name('admin.logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/profile', [AdminController::class, 'AdminProfile'])->name('admin.profile');

    Route::post('/profile/store', [AdminController::class, 'ProfileStore'])->name('profile.store');

    Route::post('/admin/password/update', [AdminController::class, 'AdminPasswordUpdate'])->name('admin.password.update');
});

Route::middleware(['auth', 'admin', 'can:accounts.manage'])->group(function () {
    Route::controller(PaymentAccountController::class)->group(function () {
        // Payment Accounts
        Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])->name('payment.accounts');
        Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])->name('payment.account.store');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->name('payment.account.update');
        Route::patch('/payment-accounts/{id}/toggle-active', [PaymentAccountController::class, 'toggleActive'])->name('payment.account.toggle');
        Route::get('/payment-accounts/{id}/book', [PaymentAccountController::class, 'book'])->name('payment.account.book');
        Route::post('/payment-accounts/fund-transfer', [PaymentAccountController::class, 'fundTransfer'])->name('payment.account.fund.transfer');
        Route::post('/payment-accounts/deposit', [PaymentAccountController::class, 'deposit'])->name('payment.account.deposit');

        // Account-book transactions (deposits & fund transfers only)
        Route::put('/transactions/{id}', [PaymentAccountController::class, 'transactionUpdate'])->name('transaction.update');
        Route::delete('/transactions/{id}', [PaymentAccountController::class, 'transactionDestroy'])->name('transaction.delete');

        // Account Types
        Route::post('/account-types', [AccountTypeController::class, 'store'])->name('account.type.store');
        Route::put('/account-types/{id}', [AccountTypeController::class, 'update'])->name('account.type.update');
        Route::delete('/account-types/{id}', [AccountTypeController::class, 'destroy'])->name('account.type.delete');
    });
});

// Contacts: suppliers, customers & customer groups
Route::middleware(['auth', 'admin', 'can:contacts.manage'])->group(function () {
    // Suppliers & Customers share one controller (Contact) filtered by type.
    Route::get('/suppliers', [ContactController::class, 'suppliers'])->name('suppliers.index');
    Route::get('/customers', [ContactController::class, 'customers'])->name('customers.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contact.store');
    Route::put('/contacts/{id}', [ContactController::class, 'update'])->name('contact.update');
    Route::patch('/contacts/{id}/toggle-active', [ContactController::class, 'toggleActive'])->name('contact.toggle');
    Route::post('/customers/{id}/create-login', [ContactController::class, 'createLogin'])->name('contact.create.login');
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->name('contact.delete');

    // Customer Groups
    Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer.groups');
    Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('customer.group.store');
    Route::put('/customer-groups/{id}', [CustomerGroupController::class, 'update'])->name('customer.group.update');
    Route::delete('/customer-groups/{id}', [CustomerGroupController::class, 'destroy'])->name('customer.group.delete');
});

// Products & Inventory: categories, brands, units, warehouses, products
Route::middleware(['auth', 'admin', 'can:products.manage'])->group(function () {
    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('category.delete');

    // Brands
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->name('brand.store');
    Route::put('/brands/{id}', [BrandController::class, 'update'])->name('brand.update');
    Route::delete('/brands/{id}', [BrandController::class, 'destroy'])->name('brand.delete');

    // Units
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('unit.store');
    Route::put('/units/{id}', [UnitController::class, 'update'])->name('unit.update');
    Route::delete('/units/{id}', [UnitController::class, 'destroy'])->name('unit.delete');

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::put('/warehouses/{id}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])->name('warehouse.delete');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('product.store');
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('product.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('product.delete');
});

// Quotations (+ admin-managed lookups)
Route::middleware(['auth', 'admin', 'can:quotations.manage'])->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/requests', [QuotationController::class, 'requests'])->name('quotation.requests');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotation.store');
    Route::post('/quotations/parse-packing-list', [QuotationController::class, 'parsePackingList'])->name('quotation.parse.packing');
    Route::get('/quotations/{id}', [QuotationController::class, 'show'])->name('quotation.show');
    Route::get('/quotations/{id}/edit', [QuotationController::class, 'edit'])->name('quotation.edit');
    Route::put('/quotations/{id}', [QuotationController::class, 'update'])->name('quotation.update');
    Route::post('/quotations/{id}/deny', [QuotationController::class, 'deny'])->name('quotation.deny');
    Route::delete('/quotations/{id}', [QuotationController::class, 'destroy'])->name('quotation.delete');

    // Transportation modes (admin-managed dropdown)
    Route::get('/transportation-modes', [TransportationModeController::class, 'index'])->name('transportation.modes');
    Route::post('/transportation-modes', [TransportationModeController::class, 'store'])->name('transportation.mode.store');
    Route::put('/transportation-modes/{id}', [TransportationModeController::class, 'update'])->name('transportation.mode.update');
    Route::delete('/transportation-modes/{id}', [TransportationModeController::class, 'destroy'])->name('transportation.mode.delete');

    // Packing types (admin-managed dropdown)
    Route::get('/packing-types', [PackingTypeController::class, 'index'])->name('packing.types');
    Route::post('/packing-types', [PackingTypeController::class, 'store'])->name('packing.type.store');
    Route::put('/packing-types/{id}', [PackingTypeController::class, 'update'])->name('packing.type.update');
    Route::delete('/packing-types/{id}', [PackingTypeController::class, 'destroy'])->name('packing.type.delete');
});

// Orders — full management (create/edit/delete)
Route::middleware(['auth', 'admin', 'can:orders.manage'])->group(function () {
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('order.store');
    Route::post('/orders/from-quotation/{id}', [OrderController::class, 'fromQuotation'])->name('order.from.quotation');
    Route::post('/orders/{id}/payment', [OrderController::class, 'storePayment'])->name('order.payment');
    Route::get('/orders/{id}/edit', [OrderController::class, 'edit'])->name('order.edit');
    Route::put('/orders/{id}', [OrderController::class, 'update'])->name('order.update');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('order.delete');
});

// Orders — view & status updates (managers + warehouse/port staff)
Route::middleware(['auth', 'admin', 'can:orders.view'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('order.show');
    Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice'])->name('order.invoice');
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('order.status');
});

// Order Costs — categorized costs on an order (feeds profit; can debit a payment account)
Route::middleware(['auth', 'admin', 'can:costs.manage'])->group(function () {
    Route::post('/orders/{id}/cost', [OrderController::class, 'storeCost'])->name('order.cost.store');
    Route::delete('/orders/{id}/cost/{costId}', [OrderController::class, 'destroyCost'])->name('order.cost.delete');

    // Cost categories (admin-managed dropdown)
    Route::get('/cost-categories', [CostCategoryController::class, 'index'])->name('cost.categories');
    Route::post('/cost-categories', [CostCategoryController::class, 'store'])->name('cost.category.store');
    Route::put('/cost-categories/{id}', [CostCategoryController::class, 'update'])->name('cost.category.update');
    Route::delete('/cost-categories/{id}', [CostCategoryController::class, 'destroy'])->name('cost.category.delete');
});

// Warehouse expense categories (admin-managed, parent -> sub; warehouses pick from these)
Route::middleware(['auth', 'admin', 'can:expenses.manage'])->group(function () {
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense.categories');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense.category.store');
    Route::put('/expense-categories/{id}', [ExpenseCategoryController::class, 'update'])->name('expense.category.update');
    Route::delete('/expense-categories/{id}', [ExpenseCategoryController::class, 'destroy'])->name('expense.category.delete');
});

// LC — Letters of Credit (created from a supplier's purchase invoice against an order)
Route::middleware(['auth', 'admin', 'can:lc.manage'])->group(function () {
    Route::get('/lcs', [LcController::class, 'index'])->name('lc.index');
    Route::get('/lcs/create', [LcController::class, 'create'])->name('lc.create');
    Route::post('/lcs', [LcController::class, 'store'])->name('lc.store');
    Route::get('/lcs/{id}', [LcController::class, 'show'])->name('lc.show');
    Route::post('/lcs/{id}/status', [LcController::class, 'updateStatus'])->name('lc.status');
    Route::get('/lcs/{id}/edit', [LcController::class, 'edit'])->name('lc.edit');
    Route::put('/lcs/{id}', [LcController::class, 'update'])->name('lc.update');
    Route::delete('/lcs/{id}', [LcController::class, 'destroy'])->name('lc.delete');

    // LC charge lines (bank/LC fees that feed the order's cost)
    Route::post('/lcs/{id}/cost', [LcController::class, 'storeCost'])->name('lc.cost.store');
    Route::delete('/lcs/{id}/cost/{costId}', [LcController::class, 'destroyCost'])->name('lc.cost.delete');
});

// Containers & Shipments (group orders into a container; shared costs distributed to orders)
Route::middleware(['auth', 'admin', 'can:containers.manage'])->group(function () {
    Route::get('/containers', [ContainerController::class, 'index'])->name('container.index');
    Route::get('/containers/create', [ContainerController::class, 'create'])->name('container.create');
    Route::post('/containers', [ContainerController::class, 'store'])->name('container.store');
    Route::get('/containers/{id}', [ContainerController::class, 'show'])->name('container.show');
    Route::get('/containers/{id}/edit', [ContainerController::class, 'edit'])->name('container.edit');
    Route::put('/containers/{id}', [ContainerController::class, 'update'])->name('container.update');
    Route::delete('/containers/{id}', [ContainerController::class, 'destroy'])->name('container.delete');
    Route::post('/containers/{id}/status', [ContainerController::class, 'updateStatus'])->name('container.status');

    // Assigned orders
    Route::post('/containers/{id}/order', [ContainerController::class, 'storeOrder'])->name('container.order.store');
    Route::delete('/containers/{id}/order/{orderId}', [ContainerController::class, 'destroyOrder'])->name('container.order.delete');

    // Container costs (distributed to member orders)
    Route::post('/containers/{id}/cost', [ContainerController::class, 'storeCost'])->name('container.cost.store');
    Route::delete('/containers/{id}/cost/{costId}', [ContainerController::class, 'destroyCost'])->name('container.cost.delete');

    // Documents (B/L, packing list, invoice, ...)
    Route::post('/containers/{id}/document', [ContainerController::class, 'storeDocument'])->name('container.document.store');
    Route::delete('/containers/{id}/document/{docId}', [ContainerController::class, 'destroyDocument'])->name('container.document.delete');

    // Generated printable lists
    Route::get('/containers/{id}/packing-list', [ContainerController::class, 'packingList'])->name('container.packing.list');
    Route::get('/containers/{id}/loading-list', [ContainerController::class, 'loadingList'])->name('container.loading.list');
});

// Reports (profit & loss, receivables, balance sheet, cash flow)
Route::middleware(['auth', 'admin', 'can:reports.view'])->group(function () {
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/warehouse-summary', [ReportController::class, 'warehouseSummary'])->name('reports.warehouse-summary');
});

// Company / invoice settings (admin)
Route::middleware(['auth', 'admin', 'can:settings.manage'])->group(function () {
    Route::get('/settings/company', [CompanySettingController::class, 'edit'])->name('settings.company');
    Route::post('/settings/company', [CompanySettingController::class, 'update'])->name('settings.company.update');
});

// Access Control: users & roles (admin-managed)
Route::middleware(['auth', 'admin'])->group(function () {
    // Users
    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('user.store');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('user.update');
        Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('user.toggle');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('user.delete');
    });

    // Roles & permissions
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('role.store');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('role.update');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('role.delete');
    });
});
