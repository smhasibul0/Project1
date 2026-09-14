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
        // Ability strings ARE the permission keys from App\Support\PermissionCatalog, so
        // `@can('orders.create')` and route middleware `can:orders.create` both flow
        // through here — there are no composite or hard-coded abilities.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
