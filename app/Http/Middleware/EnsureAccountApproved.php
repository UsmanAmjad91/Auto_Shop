<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks protected areas until an administrator approves the account.
 * Rejected / suspended / disabled users are signed out.
 */
class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->isApproved()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Account is '.$user->status.'.'], 403);
        }

        if ($user->status === 'pending') {
            return redirect()->route('account.pending');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'Your account is '.$user->status.'. Contact an administrator.']);
    }
}
