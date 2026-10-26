<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /** Exchange credentials for a bearer token. 2FA users must create tokens from the web UI. */
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['required', 'string', 'max:100']]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            LoginHistory::create(['user_id' => $user?->id, 'email' => $data['email'], 'ip_address' => $request->ip(), 'successful' => false, 'reason' => 'api_invalid_credentials']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        if (! $user->isApproved()) {
            throw ValidationException::withMessages(['email' => 'Account is '.$user->status.'.']);
        }
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['email' => 'Two-factor accounts must create API tokens in Profile > Security.']);
        }
        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'ip_address' => $request->ip(), 'successful' => true,
            'reason' => 'api_token', 'logged_in_at' => now()]);

        return response()->json(['token' => $user->createToken($data['device_name'], ['*'], now()->addDays(90))->plainTextToken, 'expires_in_days' => 90]);
    }

    public function me(Request $request)
    {
        $u = $request->user();

        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'roles' => $u->getRoleNames(), 'permissions' => $u->getAllPermissions()->pluck('name')];
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
