<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Backend\PaymentAccountController;
use App\Http\Controllers\Backend\AccountTypeController;

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
        Route::get('/payment-accounts',               [PaymentAccountController::class, 'index'])->name('payment.accounts');
        Route::post('/payment-accounts',              [PaymentAccountController::class, 'store'])->name('payment.account.store');
        Route::get('/payment-accounts/{id}/edit',     [PaymentAccountController::class, 'edit'])->name('payment.account.edit');
        Route::put('/payment-accounts/{id}',          [PaymentAccountController::class, 'update'])->name('payment.account.update');
        Route::patch('/payment-accounts/{id}/close',  [PaymentAccountController::class, 'close'])->name('payment.account.close');
        Route::get('/payment-accounts/{id}/book',     [PaymentAccountController::class, 'book'])->name('payment.account.book');
        Route::post('/payment-accounts/fund-transfer',[PaymentAccountController::class, 'fundTransfer'])->name('payment.account.fund.transfer');
        Route::post('/payment-accounts/deposit',      [PaymentAccountController::class, 'deposit'])->name('payment.account.deposit');
        
        // Account Types
        Route::post('/account-types',         [AccountTypeController::class, 'store'])->name('account.type.store');
        Route::get('/account-types/{id}/edit',[AccountTypeController::class, 'edit'])->name('account.type.edit');
        Route::put('/account-types/{id}',     [AccountTypeController::class, 'update'])->name('account.type.update');
        Route::delete('/account-types/{id}',  [AccountTypeController::class, 'destroy'])->name('account.type.delete');
    });
});
