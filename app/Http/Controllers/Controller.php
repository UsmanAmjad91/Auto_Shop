<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /** Respond with JSON for AJAX callers or redirect back with a flash message. */
    protected function done(Request $request, string $message, ?string $redirect = null, array $data = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $redirect] + $data);
        }

        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    /** Abort 403 unless the user holds at least one of the permissions. */
    protected function requireAny(Request $request, string ...$permissions): void
    {
        foreach ($permissions as $p) {
            if ($request->user()->can($p)) {
                return;
            }
        }
        abort(403);
    }
}
