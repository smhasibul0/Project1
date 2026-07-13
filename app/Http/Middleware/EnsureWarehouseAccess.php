<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseAccess
{
    /**
     * Restrict the warehouse portal to warehouse logins linked to a warehouse.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isWarehouse() || ! $user->warehouse_id) {
            abort(403, 'This area is for warehouse accounts.');
        }

        return $next($request);
    }
}
