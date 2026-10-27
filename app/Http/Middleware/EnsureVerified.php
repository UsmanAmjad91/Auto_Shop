<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Enforces email and/or SMS verification when enabled in Settings > Verification. */
class EnsureVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (settings()->bool('verification.email') && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email address is not verified.'], 403)
                : redirect()->route('verification.notice');
        }

        if (settings()->bool('verification.sms') && ! $user->phone_verified_at) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Phone number is not verified.'], 403)
                : redirect()->route('phone.verify');
        }

        return $next($request);
    }
}
