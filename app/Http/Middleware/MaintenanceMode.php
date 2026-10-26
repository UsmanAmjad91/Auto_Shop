<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Application-level maintenance mode that still lets administrators work. */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (settings()->bool('maintenance.enabled') && ! $request->user()?->can('settings.manage')
            && ! $request->routeIs('login', 'login.store', 'logout')) {
            $message = settings('maintenance.message');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : response()->view('errors.maintenance', ['message' => $message], 503);
        }

        return $next($request);
    }
}
