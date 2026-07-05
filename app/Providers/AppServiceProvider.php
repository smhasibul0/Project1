<?php

namespace App\Providers;

use App\Models\User;
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
        // Resolve every ability against the user's role permissions. Admin bypasses all.
        // Ability strings ARE the permission keys (e.g. "orders.manage"), so `@can('orders.manage')`
        // and route middleware `can:orders.manage` both flow through here.
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
