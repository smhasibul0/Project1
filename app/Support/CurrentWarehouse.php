<?php

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves which warehouse the current request operates on: a warehouse login's
 * own warehouse, or — for admins — the warehouse they picked via "Manage" on
 * the admin warehouse list (kept in the session).
 */
class CurrentWarehouse
{
    public const SESSION_KEY = 'managed_warehouse_id';

    public static function id(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->isWarehouse()) {
            return $user->warehouse_id;
        }

        if ($user->isAdmin()) {
            return session(self::SESSION_KEY);
        }

        return null;
    }

    public static function get(): ?Warehouse
    {
        $id = self::id();

        return $id ? Warehouse::find($id) : null;
    }

    /**
     * Whether an admin is currently managing a warehouse from the admin panel.
     */
    public static function isManaging(): bool
    {
        return (bool) (Auth::user()?->isAdmin() && session(self::SESSION_KEY));
    }
}
