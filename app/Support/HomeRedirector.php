<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Resolves the post-login landing page for an authenticated user.
 *
 * The application has no dashboard route. Landing pages are chosen from the
 * routes that actually exist and from the role gate each route declares:
 *  - Admin:  user.list   (the only route gated by the "admin" middleware)
 *  - Others: report.form (auth-only route, accessible to every signed-in user)
 */
class HomeRedirector
{
    public const ADMIN_ROUTE = 'user.list';

    public const DEFAULT_ROUTE = 'report.form';

    /**
     * Route name that the given user should land on after authentication.
     */
    public static function routeNameFor(?User $user): string
    {
        $isAdmin = $user !== null && ($user->role->code ?? null) === Role::ADMIN;

        $preferred = $isAdmin ? self::ADMIN_ROUTE : self::DEFAULT_ROUTE;

        if (Route::has($preferred)) {
            return $preferred;
        }

        // Never redirect to a route that does not exist: fall back to the
        // other landing page, then to the login page as a last resort.
        return Route::has(self::DEFAULT_ROUTE)
            ? self::DEFAULT_ROUTE
            : 'auth.login';
    }

    /**
     * Absolute URL for the given user's landing page.
     */
    public static function urlFor(?User $user): string
    {
        return route(self::routeNameFor($user));
    }
}
