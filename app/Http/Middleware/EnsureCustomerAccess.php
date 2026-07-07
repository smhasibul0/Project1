<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccess
{
    /**
     * Restrict the portal to customer logins that are linked to a business record.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isCustomer() || ! $user->contact_id) {
            abort(403, 'This area is for customer accounts.');
        }

        return $next($request);
    }
}
