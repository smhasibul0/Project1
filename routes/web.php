<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Backend\AccountTypeController;
use App\Http\Controllers\Backend\BrandController;
use App\Http\Controllers\Backend\CategoryController;
use App\Http\Controllers\Backend\CompanySettingController;
use App\Http\Controllers\Backend\ContactController;
use App\Http\Controllers\Backend\CostCategoryController;
use App\Http\Controllers\Backend\CustomerGroupController;
use App\Http\Controllers\Backend\LcController;
use App\Http\Controllers\Backend\OrderController;
use App\Http\Controllers\Backend\PackingTypeController;
use App\Http\Controllers\Backend\PaymentAccountController;
use App\Http\Controllers\Backend\ProductController;
use App\Http\Controllers\Backend\QuotationController;
use App\Http\Controllers\Backend\RoleController;
use App\Http\Controllers\Backend\TransportationModeController;
use App\Http\Controllers\Backend\UnitController;
use App\Http\Controllers\Backend\UserController;
use App\Http\Controllers\Backend\WarehouseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public order tracking (no login required)
Route::get('/track', [TrackController::class, 'index'])->name('order.track');

Route::get('/dashboard', function () {
    return view('admin.index');
})->middleware(['auth', 'admin', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
        Route::get('/payment-accounts/{id}/edit', [PaymentAccountController::class, 'edit'])->name('payment.account.edit');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->name('payment.account.update');
        Route::patch('/payment-accounts/{id}/close', [PaymentAccountController::class, 'close'])->name('payment.account.close');
        Route::get('/payment-accounts/{id}/book', [PaymentAccountController::class, 'book'])->name('payment.account.book');
        Route::post('/payment-accounts/fund-transfer', [PaymentAccountController::class, 'fundTransfer'])->name('payment.account.fund.transfer');
        Route::post('/payment-accounts/deposit', [PaymentAccountController::class, 'deposit'])->name('payment.account.deposit');

        // Account-book transactions (deposits & fund transfers only)
        Route::put('/transactions/{id}', [PaymentAccountController::class, 'transactionUpdate'])->name('transaction.update');
        Route::delete('/transactions/{id}', [PaymentAccountController::class, 'transactionDestroy'])->name('transaction.delete');

        // Account Types
        Route::post('/account-types', [AccountTypeController::class, 'store'])->name('account.type.store');
        Route::get('/account-types/{id}/edit', [AccountTypeController::class, 'edit'])->name('account.type.edit');
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
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotation.store');
    Route::get('/quotations/{id}', [QuotationController::class, 'show'])->name('quotation.show');
    Route::get('/quotations/{id}/edit', [QuotationController::class, 'edit'])->name('quotation.edit');
    Route::put('/quotations/{id}', [QuotationController::class, 'update'])->name('quotation.update');
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
