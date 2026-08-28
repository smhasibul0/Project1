<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The admin theme is Bootstrap 5; the tariff list is the one paged screen.
        Paginator::useBootstrapFive();

        // Resolve every ability against the user's role permissions. Admin bypasses all.
        // Ability strings ARE the permission keys (e.g. "orders.manage"), so `@can('orders.manage')`
        // and route middleware `can:orders.manage` both flow through here.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        // Composite: anyone who can fully manage, just update-status, or manage costs
        // (accountants add costs on the order page) may view orders.
        Gate::define('orders.view', function (User $user) {
            return $user->hasPermission('orders.manage')
                || $user->hasPermission('orders.update-status')
                || $user->hasPermission('costs.manage');
        });

        // Payment account balances are admin-only. Warehouse managers and other
        // staff pick an account to pay from without seeing what it holds; the
        // Gate::before hook above grants admins every ability, so this closure
        // only ever runs for non-admins.
        Gate::define('accounts.view-balance', function (User $user) {
            return false;
        });
    }
}
