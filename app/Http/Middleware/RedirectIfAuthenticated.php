<?php

namespace App\Http\Middleware;

use App\Support\HomeRedirector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Authenticated users are sent to their role-appropriate landing page.
     * The previous implementation redirected to RouteServiceProvider::HOME
     * ('/report/home'), a route that does not exist, which produced a 404.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect(HomeRedirector::urlFor(Auth::guard($guard)->user()));
            }
        }

        return $next($request);
    }
}
