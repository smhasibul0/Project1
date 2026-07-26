<?php

namespace App\Http\Middleware;

use App\Support\CurrentWarehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseAccess
{
    /**
     * Restrict the warehouse portal to warehouse logins linked to a warehouse,
     * or admins who picked a warehouse to manage from the admin panel.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isWarehouse() && $user->warehouse_id) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            if (CurrentWarehouse::get()) {
                return $next($request);
            }

            return redirect()->route('warehouses.index')
                ->with('error', 'Choose a warehouse to manage first.');
        }

        abort(403, 'This area is for warehouse accounts.');
    }
}
