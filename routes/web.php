<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Backend\AccountTypeController;
use App\Http\Controllers\Backend\ActivityLogController;
use App\Http\Controllers\Backend\AssetCategoryController;
use App\Http\Controllers\Backend\AssetController;
use App\Http\Controllers\Backend\AssetDepreciationController;
use App\Http\Controllers\Backend\CompanySettingController;
use App\Http\Controllers\Backend\ContactController;
use App\Http\Controllers\Backend\ContainerController;
use App\Http\Controllers\Backend\CostCategoryController;
use App\Http\Controllers\Backend\CustomerGroupController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\ExchangeRateController;
use App\Http\Controllers\Backend\ExpenseCategoryController;
use App\Http\Controllers\Backend\HsCodeController;
use App\Http\Controllers\Backend\LcController;
use App\Http\Controllers\Backend\LoanController;
use App\Http\Controllers\Backend\OfficeCostTypeController;
use App\Http\Controllers\Backend\OfficeExpenseController;
use App\Http\Controllers\Backend\OrderController;
use App\Http\Controllers\Backend\PackingTypeController;
use App\Http\Controllers\Backend\PaymentAccountController;
use App\Http\Controllers\Backend\QuotationController;
use App\Http\Controllers\Backend\ReportController;
use App\Http\Controllers\Backend\RoleController;
use App\Http\Controllers\Backend\TransportationModeController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\ValuationRateController;
use App\Http\Controllers\Backend\WarehouseController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Http\Controllers\Warehouse\ExpenseController as WarehouseExpenseController;
use App\Http\Controllers\Warehouse\InventoryController as WarehouseInventoryController;
use App\Http\Controllers\Warehouse\OrderController as WarehouseOrderController;
use App\Http\Controllers\Warehouse\PayrollController as WarehousePayrollController;
use App\Http\Controllers\Warehouse\StaffController as WarehouseStaffController;
use Illuminate\Support\Facades\Route;

// No public home page. The dashboard route already sends a guest to the login screen
// and customers and warehouse staff on to their own portals.
Route::get('/', fn () => redirect()->route('dashboard'));

// Public order tracking (no login required). The token in the link is the only
// way in — there is no lookup by order number, which would be guessable. This is
// also what the QR code on a carton opens: strangers see the timeline, staff who
// can scan get the panel for counting cartons through the next stage.
Route::get('/track/{token}', [TrackController::class, 'show'])->name('order.track');

// Carton scanning. Recording runs through the camera scanner — the key that
// authorises it is handed out when a carton's QR is decoded, so nothing can be
// written down from a desk.
Route::middleware(['auth', 'can:orders.scan'])->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scan/{token}', [ScanController::class, 'lookup'])->name('scan.lookup');
    Route::post('/track/{token}/scan', [TrackController::class, 'scan'])->name('order.scan');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'admin', 'verified', 'can:dashboard.view'])->name('dashboard');

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
    Route::get('/', [WarehouseDashboardController::class, 'index'])->middleware('can:warehouse.dashboard.view')->name('dashboard');

    Route::middleware('can:warehouse.inventory.view')->group(function () {
        Route::get('/inventory', [WarehouseInventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{id}', [WarehouseInventoryController::class, 'show'])->name('inventory.show');
    });
    Route::post('/inventory/{id}/dispatch', [WarehouseInventoryController::class, 'dispatch'])->middleware('can:warehouse.inventory.dispatch')->name('inventory.dispatch');

    Route::middleware('can:warehouse.orders.view')->group(function () {
        Route::get('/orders', [WarehouseOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [WarehouseOrderController::class, 'show'])->name('orders.show');
    });

    Route::get('/expenses', [WarehouseExpenseController::class, 'index'])->middleware('can:warehouse.expenses.view')->name('expenses.index');
    Route::post('/expenses', [WarehouseExpenseController::class, 'store'])->middleware('can:warehouse.expenses.create')->name('expenses.store');
    Route::put('/expenses/{id}', [WarehouseExpenseController::class, 'update'])->middleware('can:warehouse.expenses.edit')->name('expenses.update');
    Route::delete('/expenses/{id}', [WarehouseExpenseController::class, 'destroy'])->middleware('can:warehouse.expenses.delete')->name('expenses.delete');
    Route::post('/expenses/{id}/payments', [WarehouseExpenseController::class, 'storePayment'])->middleware('can:warehouse.expenses.payments.create')->name('expenses.payments.store');
    Route::delete('/expenses/{id}/payments/{paymentId}', [WarehouseExpenseController::class, 'destroyPayment'])->middleware('can:warehouse.expenses.payments.delete')->name('expenses.payments.delete');

    Route::get('/payroll', [WarehousePayrollController::class, 'index'])->middleware('can:warehouse.payroll.view')->name('payroll.index');

    Route::get('/staff', [WarehouseStaffController::class, 'index'])->middleware('can:warehouse.staff.view')->name('staff.index');
    Route::get('/staff/{id}', [WarehouseStaffController::class, 'show'])->middleware('can:warehouse.staff.view')->name('staff.show');
    Route::post('/staff', [WarehouseStaffController::class, 'store'])->middleware('can:warehouse.staff.create')->name('staff.store');
    Route::put('/staff/{id}', [WarehouseStaffController::class, 'update'])->middleware('can:warehouse.staff.edit')->name('staff.update');
    Route::delete('/staff/{id}', [WarehouseStaffController::class, 'destroy'])->middleware('can:warehouse.staff.delete')->name('staff.delete');
    Route::post('/staff/{id}/documents', [WarehouseStaffController::class, 'storeDocument'])->middleware('can:warehouse.staff.documents.upload')->name('staff.document.store');
    Route::delete('/staff/{id}/documents/{documentId}', [WarehouseStaffController::class, 'destroyDocument'])->middleware('can:warehouse.staff.documents.delete')->name('staff.document.delete');
    Route::post('/staff/{id}/salary', [WarehouseStaffController::class, 'storeSalary'])->middleware('can:warehouse.staff.salary.create')->name('staff.salary.store');
    Route::delete('/staff/{id}/salary/{paymentId}', [WarehouseStaffController::class, 'destroySalary'])->middleware('can:warehouse.staff.salary.delete')->name('staff.salary.delete');
});

require __DIR__.'/auth.php';

Route::get('/admin/logout', [AdminController::class, 'AdminLogout'])->name('admin.logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/profile', [AdminController::class, 'AdminProfile'])->name('admin.profile');

    Route::post('/profile/store', [AdminController::class, 'ProfileStore'])->name('profile.store');

    Route::post('/admin/password/update', [AdminController::class, 'AdminPasswordUpdate'])->name('admin.password.update');
});

Route::middleware(['auth', 'admin'])->group(function () {
    // Payment Accounts
    Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])->middleware('can:accounts.view')->name('payment.accounts');
    Route::get('/payment-accounts/{id}/book', [PaymentAccountController::class, 'book'])->middleware('can:accounts.view')->name('payment.account.book');
    Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])->middleware('can:accounts.create')->name('payment.account.store');
    Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->middleware('can:accounts.edit')->name('payment.account.update');
    Route::patch('/payment-accounts/{id}/toggle-active', [PaymentAccountController::class, 'toggleActive'])->middleware('can:accounts.toggle')->name('payment.account.toggle');
    Route::post('/payment-accounts/deposit', [PaymentAccountController::class, 'deposit'])->middleware('can:accounts.deposit')->name('payment.account.deposit');
    Route::post('/payment-accounts/fund-transfer', [PaymentAccountController::class, 'fundTransfer'])->middleware('can:accounts.fund-transfer')->name('payment.account.fund.transfer');

    // Account-book transactions (deposits & fund transfers only)
    Route::put('/transactions/{id}', [PaymentAccountController::class, 'transactionUpdate'])->middleware('can:accounts.transactions.edit')->name('transaction.update');
    Route::delete('/transactions/{id}', [PaymentAccountController::class, 'transactionDestroy'])->middleware('can:accounts.transactions.delete')->name('transaction.delete');

    // Account Types
    Route::post('/account-types', [AccountTypeController::class, 'store'])->middleware('can:account-types.create')->name('account.type.store');
    Route::put('/account-types/{id}', [AccountTypeController::class, 'update'])->middleware('can:account-types.edit')->name('account.type.update');
    Route::delete('/account-types/{id}', [AccountTypeController::class, 'destroy'])->middleware('can:account-types.delete')->name('account.type.delete');
});

// Customers & customer groups
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/customers', [ContactController::class, 'customers'])->middleware('can:customers.view')->name('customers.index');
    Route::get('/customers/shipping-mark', [ContactController::class, 'shippingMarkSuggestion'])->middleware('can:customers.view')->name('customer.shipping.mark');
    Route::post('/contacts', [ContactController::class, 'store'])->middleware('can:customers.create')->name('contact.store');
    Route::put('/contacts/{id}', [ContactController::class, 'update'])->middleware('can:customers.edit')->name('contact.update');
    Route::patch('/contacts/{id}/toggle-active', [ContactController::class, 'toggleActive'])->middleware('can:customers.toggle')->name('contact.toggle');
    Route::post('/customers/{id}/create-login', [ContactController::class, 'createLogin'])->middleware('can:customers.create-login')->name('contact.create.login');
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->middleware('can:customers.delete')->name('contact.delete');

    // Customer Groups
    Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->middleware('can:customer-groups.view')->name('customer.groups');
    Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->middleware('can:customer-groups.create')->name('customer.group.store');
    Route::put('/customer-groups/{id}', [CustomerGroupController::class, 'update'])->middleware('can:customer-groups.edit')->name('customer.group.update');
    Route::delete('/customer-groups/{id}', [CustomerGroupController::class, 'destroy'])->middleware('can:customer-groups.delete')->name('customer.group.delete');
});

// HS code lookup — available to anyone signed in (admin panel & customer portal
// both fill item rows from it); the tariff itself is public information.
Route::middleware('auth')->get('/hs-codes/search', [HsCodeController::class, 'search'])->name('hs.code.search');

// HS codes / customs tariff: bulk import from the published book + manual upkeep
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/hs-codes', [HsCodeController::class, 'index'])->middleware('can:hs.view')->name('hs.codes');
    Route::post('/hs-codes', [HsCodeController::class, 'store'])->middleware('can:hs.create')->name('hs.code.store');
    Route::post('/hs-codes/import', [HsCodeController::class, 'import'])->middleware('can:hs.import')->name('hs.code.import');
    Route::put('/hs-codes/{id}', [HsCodeController::class, 'update'])->middleware('can:hs.edit')->name('hs.code.update');
    Route::delete('/hs-codes/{id}', [HsCodeController::class, 'destroy'])->middleware('can:hs.delete')->name('hs.code.delete');
});

// Rates: Customs valuation reports, the reference declared value per HS code
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/rates', [ValuationRateController::class, 'index'])->middleware('can:rates.view')->name('rates.index');
    Route::post('/rates', [ValuationRateController::class, 'store'])->middleware('can:rates.upload')->name('rates.store');
    Route::get('/rates/{id}', [ValuationRateController::class, 'show'])->middleware('can:rates.view')->name('rates.show');
    Route::get('/rates/{id}/pdf', [ValuationRateController::class, 'pdf'])->middleware('can:rates.view')->name('rates.pdf');
    Route::delete('/rates/{id}', [ValuationRateController::class, 'destroy'])->middleware('can:rates.delete')->name('rates.delete');
});

// Exchange rates: the dollar rate for each day
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->middleware('can:exchange-rates.view')->name('exchange.rates');
    Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->middleware('can:exchange-rates.create')->name('exchange.rate.store');
    Route::put('/exchange-rates/{id}', [ExchangeRateController::class, 'update'])->middleware('can:exchange-rates.edit')->name('exchange.rate.update');
    Route::delete('/exchange-rates/{id}', [ExchangeRateController::class, 'destroy'])->middleware('can:exchange-rates.delete')->name('exchange.rate.delete');
});

// Warehouses
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('can:warehouses.view')->name('warehouses.index');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('can:warehouses.create')->name('warehouse.store');
    Route::put('/warehouses/{id}', [WarehouseController::class, 'update'])->middleware('can:warehouses.edit')->name('warehouse.update');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy'])->middleware('can:warehouses.delete')->name('warehouse.delete');
    Route::get('/warehouses/{id}/manage', [WarehouseController::class, 'manage'])->middleware('can:warehouses.enter')->name('warehouse.manage');
    Route::get('/warehouses/manage/exit', [WarehouseController::class, 'exitManage'])->middleware('can:warehouses.enter')->name('warehouse.manage.exit');
});

// Quotations (+ admin-managed lookups)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('can:quotations.view')->name('quotations.index');
    Route::get('/quotations/requests', [QuotationController::class, 'requests'])->middleware('can:quotations.view')->name('quotation.requests');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->middleware('can:quotations.create')->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->middleware('can:quotations.create')->name('quotation.store');
    Route::post('/quotations/parse-packing-list', [QuotationController::class, 'parsePackingList'])->middleware('can:quotations.create')->name('quotation.parse.packing');
    Route::get('/quotations/{id}', [QuotationController::class, 'show'])->middleware('can:quotations.view')->name('quotation.show');
    Route::get('/quotations/{id}/print', [QuotationController::class, 'print'])->middleware('can:quotations.print')->name('quotation.print');
    Route::get('/quotations/{id}/edit', [QuotationController::class, 'edit'])->middleware('can:quotations.edit')->name('quotation.edit');
    Route::put('/quotations/{id}', [QuotationController::class, 'update'])->middleware('can:quotations.edit')->name('quotation.update');
    Route::post('/quotations/{id}/deny', [QuotationController::class, 'deny'])->middleware('can:quotations.deny')->name('quotation.deny');
    Route::delete('/quotations/{id}', [QuotationController::class, 'destroy'])->middleware('can:quotations.delete')->name('quotation.delete');

    // Transportation modes (admin-managed dropdown)
    Route::get('/transportation-modes', [TransportationModeController::class, 'index'])->middleware('can:transportation-modes.view')->name('transportation.modes');
    Route::post('/transportation-modes', [TransportationModeController::class, 'store'])->middleware('can:transportation-modes.create')->name('transportation.mode.store');
    Route::put('/transportation-modes/{id}', [TransportationModeController::class, 'update'])->middleware('can:transportation-modes.edit')->name('transportation.mode.update');
    Route::delete('/transportation-modes/{id}', [TransportationModeController::class, 'destroy'])->middleware('can:transportation-modes.delete')->name('transportation.mode.delete');

    // Packing types (admin-managed dropdown)
    Route::get('/packing-types', [PackingTypeController::class, 'index'])->middleware('can:packing-types.view')->name('packing.types');
    Route::post('/packing-types', [PackingTypeController::class, 'store'])->middleware('can:packing-types.create')->name('packing.type.store');
    Route::put('/packing-types/{id}', [PackingTypeController::class, 'update'])->middleware('can:packing-types.edit')->name('packing.type.update');
    Route::delete('/packing-types/{id}', [PackingTypeController::class, 'destroy'])->middleware('can:packing-types.delete')->name('packing.type.delete');
});

// Orders
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->middleware('can:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware('can:orders.create')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('can:orders.create')->name('order.store');
    Route::post('/orders/from-quotation/{id}', [OrderController::class, 'fromQuotation'])->middleware('can:orders.create')->name('order.from.quotation');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('can:orders.view')->name('order.show');
    Route::get('/orders/{id}/edit', [OrderController::class, 'edit'])->middleware('can:orders.edit')->name('order.edit');
    Route::put('/orders/{id}', [OrderController::class, 'update'])->middleware('can:orders.edit')->name('order.update');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->middleware('can:orders.delete')->name('order.delete');
    Route::post('/orders/{id}/payment', [OrderController::class, 'storePayment'])->middleware('can:orders.payments.create')->name('order.payment');
    Route::get('/orders/{id}/invoice', [OrderController::class, 'invoice'])->middleware('can:orders.invoice')->name('order.invoice');
    Route::get('/orders/{id}/label', [OrderController::class, 'label'])->middleware('can:orders.label')->name('order.label');
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->middleware('can:orders.update-status')->name('order.status');
});

// Order Costs — categorized costs on an order (feeds profit; can debit a payment account)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/orders/{id}/cost', [OrderController::class, 'storeCost'])->middleware('can:costs.create')->name('order.cost.store');
    Route::delete('/orders/{id}/cost/{costId}', [OrderController::class, 'destroyCost'])->middleware('can:costs.delete')->name('order.cost.delete');

    // Cost categories (admin-managed dropdown)
    Route::get('/cost-categories', [CostCategoryController::class, 'index'])->middleware('can:cost-categories.view')->name('cost.categories');
    Route::post('/cost-categories', [CostCategoryController::class, 'store'])->middleware('can:cost-categories.create')->name('cost.category.store');
    Route::put('/cost-categories/{id}', [CostCategoryController::class, 'update'])->middleware('can:cost-categories.edit')->name('cost.category.update');
    Route::delete('/cost-categories/{id}', [CostCategoryController::class, 'destroy'])->middleware('can:cost-categories.delete')->name('cost.category.delete');
});

// Warehouse expense categories (admin-managed, parent -> sub; warehouses pick from these)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->middleware('can:expense-categories.view')->name('expense.categories');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->middleware('can:expense-categories.create')->name('expense.category.store');
    Route::put('/expense-categories/{id}', [ExpenseCategoryController::class, 'update'])->middleware('can:expense-categories.edit')->name('expense.category.update');
    Route::delete('/expense-categories/{id}', [ExpenseCategoryController::class, 'destroy'])->middleware('can:expense-categories.delete')->name('expense.category.delete');
});

// Office running costs — company-level fixed & variable expenses, settled by
// one or more payments drawn from the payment accounts.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/office-expenses', [OfficeExpenseController::class, 'index'])->middleware('can:office.expenses.view')->name('office.expenses');
    Route::post('/office-expenses', [OfficeExpenseController::class, 'store'])->middleware('can:office.expenses.create')->name('office.expense.store');
    Route::post('/office-expenses/generate', [OfficeExpenseController::class, 'generate'])->middleware('can:office.expenses.generate')->name('office.expense.generate');
    Route::put('/office-expenses/{id}', [OfficeExpenseController::class, 'update'])->middleware('can:office.expenses.edit')->name('office.expense.update');
    Route::delete('/office-expenses/{id}', [OfficeExpenseController::class, 'destroy'])->middleware('can:office.expenses.delete')->name('office.expense.delete');
    Route::post('/office-expenses/{id}/payments', [OfficeExpenseController::class, 'storePayment'])->middleware('can:office.expenses.payments.create')->name('office.expense.payments.store');
    Route::delete('/office-expenses/{id}/payments/{paymentId}', [OfficeExpenseController::class, 'destroyPayment'])->middleware('can:office.expenses.payments.delete')->name('office.expense.payments.delete');

    // Cost types carry the fixed/variable nature every expense inherits.
    Route::get('/office-cost-types', [OfficeCostTypeController::class, 'index'])->middleware('can:office.cost-types.view')->name('office.cost.types');
    Route::post('/office-cost-types', [OfficeCostTypeController::class, 'store'])->middleware('can:office.cost-types.create')->name('office.cost.type.store');
    Route::put('/office-cost-types/{id}', [OfficeCostTypeController::class, 'update'])->middleware('can:office.cost-types.edit')->name('office.cost.type.update');
    Route::delete('/office-cost-types/{id}', [OfficeCostTypeController::class, 'destroy'])->middleware('can:office.cost-types.delete')->name('office.cost.type.delete');
});

// Borrowing & lending — money taken from a bank or an individual, and money
// handed out, with the interest basis each carries.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/finance/{direction}', [LoanController::class, 'index'])
        ->whereIn('direction', ['borrowed', 'lent'])->middleware('can:loans.view')->name('loans.index');
    Route::post('/finance/{direction}', [LoanController::class, 'store'])
        ->whereIn('direction', ['borrowed', 'lent'])->middleware('can:loans.create')->name('loan.store');
    Route::put('/loans/{id}', [LoanController::class, 'update'])->middleware('can:loans.edit')->name('loan.update');
    Route::post('/loans/{id}/close', [LoanController::class, 'close'])->middleware('can:loans.close')->name('loan.close');
    Route::delete('/loans/{id}', [LoanController::class, 'destroy'])->middleware('can:loans.delete')->name('loan.delete');
    Route::post('/loans/{id}/payments', [LoanController::class, 'storePayment'])->middleware('can:loans.payments.create')->name('loan.payments.store');
    Route::delete('/loans/{id}/payments/{paymentId}', [LoanController::class, 'destroyPayment'])->middleware('can:loans.payments.delete')->name('loan.payments.delete');
});

// Fixed assets — the register of what the company owns, plus the monthly
// depreciation posted against it.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/assets', [AssetController::class, 'index'])->middleware('can:assets.view')->name('assets.index');
    Route::post('/assets', [AssetController::class, 'store'])->middleware('can:assets.create')->name('asset.store');
    Route::put('/assets/{id}', [AssetController::class, 'update'])->middleware('can:assets.edit')->name('asset.update');
    Route::post('/assets/{id}/dispose', [AssetController::class, 'dispose'])->middleware('can:assets.dispose')->name('asset.dispose');
    Route::post('/assets/{id}/restore', [AssetController::class, 'restore'])->middleware('can:assets.restore')->name('asset.restore');
    Route::delete('/assets/{id}', [AssetController::class, 'destroy'])->middleware('can:assets.delete')->name('asset.delete');

    // Depreciation is posted a month at a time, and can be reversed the same way.
    Route::get('/asset-depreciation', [AssetDepreciationController::class, 'index'])->middleware('can:assets.depreciation.view')->name('asset.depreciation');
    Route::post('/asset-depreciation/generate', [AssetDepreciationController::class, 'generate'])->middleware('can:assets.depreciation.generate')->name('asset.depreciation.generate');
    Route::delete('/asset-depreciation/month', [AssetDepreciationController::class, 'destroyMonth'])->middleware('can:assets.depreciation.delete')->name('asset.depreciation.month.delete');
    Route::delete('/asset-depreciation/{id}', [AssetDepreciationController::class, 'destroy'])->middleware('can:assets.depreciation.delete')->name('asset.depreciation.delete');

    // Categories carry the depreciation defaults new assets start from.
    Route::get('/asset-categories', [AssetCategoryController::class, 'index'])->middleware('can:asset-categories.view')->name('asset.categories');
    Route::post('/asset-categories', [AssetCategoryController::class, 'store'])->middleware('can:asset-categories.create')->name('asset.category.store');
    Route::put('/asset-categories/{id}', [AssetCategoryController::class, 'update'])->middleware('can:asset-categories.edit')->name('asset.category.update');
    Route::delete('/asset-categories/{id}', [AssetCategoryController::class, 'destroy'])->middleware('can:asset-categories.delete')->name('asset.category.delete');
});

// LC — Letters of Credit (opened against the shipper's proforma invoice for an order)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/lcs', [LcController::class, 'index'])->middleware('can:lc.view')->name('lc.index');
    Route::get('/lcs/create', [LcController::class, 'create'])->middleware('can:lc.create')->name('lc.create');
    Route::post('/lcs', [LcController::class, 'store'])->middleware('can:lc.create')->name('lc.store');
    Route::get('/lcs/{id}', [LcController::class, 'show'])->middleware('can:lc.view')->name('lc.show');
    Route::post('/lcs/{id}/status', [LcController::class, 'updateStatus'])->middleware('can:lc.update-status')->name('lc.status');
    Route::get('/lcs/{id}/edit', [LcController::class, 'edit'])->middleware('can:lc.edit')->name('lc.edit');
    Route::put('/lcs/{id}', [LcController::class, 'update'])->middleware('can:lc.edit')->name('lc.update');
    Route::delete('/lcs/{id}', [LcController::class, 'destroy'])->middleware('can:lc.delete')->name('lc.delete');

    // LC charge lines (bank/LC fees that feed the order's cost)
    Route::post('/lcs/{id}/cost', [LcController::class, 'storeCost'])->middleware('can:lc.costs.create')->name('lc.cost.store');
    Route::delete('/lcs/{id}/cost/{costId}', [LcController::class, 'destroyCost'])->middleware('can:lc.costs.delete')->name('lc.cost.delete');
});

// Containers & Shipments (group orders into a container; shared costs distributed to orders)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/containers', [ContainerController::class, 'index'])->middleware('can:containers.view')->name('container.index');
    Route::get('/containers/create', [ContainerController::class, 'create'])->middleware('can:containers.create')->name('container.create');
    Route::post('/containers', [ContainerController::class, 'store'])->middleware('can:containers.create')->name('container.store');
    Route::get('/containers/{id}', [ContainerController::class, 'show'])->middleware('can:containers.view')->name('container.show');
    Route::get('/containers/{id}/edit', [ContainerController::class, 'edit'])->middleware('can:containers.edit')->name('container.edit');
    Route::put('/containers/{id}', [ContainerController::class, 'update'])->middleware('can:containers.edit')->name('container.update');
    Route::delete('/containers/{id}', [ContainerController::class, 'destroy'])->middleware('can:containers.delete')->name('container.delete');
    Route::post('/containers/{id}/status', [ContainerController::class, 'updateStatus'])->middleware('can:containers.update-status')->name('container.status');

    // Assigned orders
    Route::post('/containers/{id}/order', [ContainerController::class, 'storeOrder'])->middleware('can:containers.orders.assign')->name('container.order.store');
    Route::delete('/containers/{id}/order/{orderId}', [ContainerController::class, 'destroyOrder'])->middleware('can:containers.orders.remove')->name('container.order.delete');

    // Container costs (distributed to member orders)
    Route::post('/containers/{id}/cost', [ContainerController::class, 'storeCost'])->middleware('can:containers.costs.create')->name('container.cost.store');
    Route::delete('/containers/{id}/cost/{costId}', [ContainerController::class, 'destroyCost'])->middleware('can:containers.costs.delete')->name('container.cost.delete');

    // Documents (B/L, packing list, invoice, ...)
    Route::post('/containers/{id}/document', [ContainerController::class, 'storeDocument'])->middleware('can:containers.documents.upload')->name('container.document.store');
    Route::delete('/containers/{id}/document/{docId}', [ContainerController::class, 'destroyDocument'])->middleware('can:containers.documents.delete')->name('container.document.delete');

    // Generated printable lists
    Route::get('/containers/{id}/packing-list', [ContainerController::class, 'packingList'])->middleware('can:containers.lists.print')->name('container.packing.list');
    Route::get('/containers/{id}/loading-list', [ContainerController::class, 'loadingList'])->middleware('can:containers.lists.print')->name('container.loading.list');
});

// Reports — each one is granted on its own
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->middleware('can:reports.profit-loss')->name('reports.profit-loss');
    Route::get('/reports/receivables', [ReportController::class, 'receivables'])->middleware('can:reports.receivables')->name('reports.receivables');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('can:reports.balance-sheet')->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->middleware('can:reports.cash-flow')->name('reports.cash-flow');
    Route::get('/reports/warehouse-summary', [ReportController::class, 'warehouseSummary'])->middleware('can:reports.warehouse-summary')->name('reports.warehouse-summary');
});

// Company / invoice settings (admin)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/settings/company', [CompanySettingController::class, 'edit'])->middleware('can:settings.view')->name('settings.company');
    Route::post('/settings/company', [CompanySettingController::class, 'update'])->middleware('can:settings.edit')->name('settings.company.update');
});

// Activity log: who added, edited, updated the status of, or deleted every record
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->middleware('can:activity.view')->name('activity.index');
});

// Access Control: users & roles (admin-managed)
Route::middleware(['auth', 'admin'])->group(function () {
    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:users.create')->name('user.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('can:users.edit')->name('user.update');
    Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->middleware('can:users.toggle')->name('user.toggle');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('user.delete');

    // Roles & permissions
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:roles.create')->name('role.store');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->middleware('can:roles.edit')->name('role.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('role.delete');
});
