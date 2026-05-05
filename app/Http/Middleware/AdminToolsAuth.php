<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminToolsAuth
{
    public const SESSION_KEY = 'admin_tools_authenticated';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get(self::SESSION_KEY, false)) {
            return redirect()->guest(route('admin.login'));
        }

        return $next($request);
    }
}
