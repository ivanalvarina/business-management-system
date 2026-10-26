<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs('password.change.*', 'logout')
        ) {
            return redirect()->route('password.change.edit');
        }

        return $next($request);
    }
}
