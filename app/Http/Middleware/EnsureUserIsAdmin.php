<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts a route to accounts whose role code is `admin`.
 *
 * The role is resolved through the `users.role_id` relation, never through a
 * hardcoded numeric id, so the check keeps working if role ids ever differ
 * between environments.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        $isAdmin = $user !== null
            && ($user->role->code ?? null) === Role::ADMIN;

        if (!$isAdmin) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
