<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Backend\AccountTypeController;
use App\Http\Controllers\Backend\ContactController;
use App\Http\Controllers\Backend\CustomerGroupController;
use App\Http\Controllers\Backend\PaymentAccountController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('admin.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/admin/logout', [AdminController::class, 'AdminLogout'])->name('admin.logout');

Route::middleware('auth')->group(function () {
    Route::get('/admin/profile', [AdminController::class, 'AdminProfile'])->name('admin.profile');

    Route::post('/profile/store', [AdminController::class, 'ProfileStore'])->name('profile.store');

    Route::post('/admin/password/update', [AdminController::class, 'AdminPasswordUpdate'])->name('admin.password.update');
});

Route::middleware('auth')->group(function () {
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
Route::middleware('auth')->group(function () {
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
