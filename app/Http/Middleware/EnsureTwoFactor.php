<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $mode = settings('verification.two_factor', 'optional');

        if ($mode !== 'off' && $user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor_passed')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Two-factor authentication required.'], 403)
                : redirect()->route('two-factor.challenge');
        }

        if ($mode === 'required' && ! $user->hasTwoFactorEnabled() && ! $request->routeIs('profile.*', 'two-factor.*', 'logout')) {
            return redirect()->route('profile.security')->with('warning', 'Two-factor authentication is required. Please set it up to continue.');
        }

        return $next($request);
    }
}
