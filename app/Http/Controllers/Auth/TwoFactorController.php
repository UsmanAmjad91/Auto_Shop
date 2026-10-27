<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor authentication: authenticator app (TOTP), email OTP or SMS OTP,
 * plus single-use recovery codes.
 */
class TwoFactorController extends Controller
{
    public function __construct(protected Google2FA $google2fa) {}

    public function challenge(Request $request)
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled() || $request->session()->get('two_factor_passed')) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor', ['method' => $user->two_factor_method]);
    }

    public function sendCode(Request $request, OtpService $otp)
    {
        $user = $request->user();
        abort_unless(in_array($user->two_factor_method, ['email', 'sms'], true), 404);
        $otp->send($user, $user->two_factor_method, 'two_factor');

        return back()->with('success', 'A new code has been sent.');
    }

    public function verify(Request $request, OtpService $otp)
    {
        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:30']]);
        $user = $request->user();

        if ($request->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $match = collect($codes)->first(fn ($hash) => Hash::check(trim($request->recovery_code), $hash));
            if (! $match) {
                throw ValidationException::withMessages(['recovery_code' => 'Invalid recovery code.']);
            }
            $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$match]))])->save();
            AuditLogger::log('2fa_recovery_used', $user, 'Recovery code used to sign in');
        } elseif ($user->two_factor_method === 'totp') {
            if (! $this->google2fa->verifyKey((string) $user->two_factor_secret, preg_replace('/\s/', '', (string) $request->code), 1)) {
                throw ValidationException::withMessages(['code' => 'Invalid authentication code.']);
            }
        } else {
            $otp->verify($user, 'two_factor', (string) $request->code);
        }

        $request->session()->put('two_factor_passed', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** Begin TOTP setup: create a secret and show the QR code (confirmed in confirm()). */
    public function setupTotp(Request $request)
    {
        $this->ensureAllowed();
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey(32);
        $request->session()->put('2fa_pending_secret', $secret);

        return redirect()->route('profile.security')->with('totp_setup', true);
    }

    public function confirm(Request $request)
    {
        $this->ensureAllowed();
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get('2fa_pending_secret');
        if (! $secret || ! $this->google2fa->verifyKey($secret, preg_replace('/\s/', '', $request->code), 1)) {
            return redirect()->route('profile.security')->with('totp_setup', true)->withErrors(['code' => 'Invalid code — scan the QR code and try again.']);
        }

        $codes = $this->newRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_method' => 'totp',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();
        $request->session()->forget('2fa_pending_secret');
        $request->session()->put('two_factor_passed', true);
        AuditLogger::log('2fa_enabled', $request->user(), 'Authenticator app 2FA enabled');

        return redirect()->route('profile.security')->with('recovery_codes', $codes)->with('success', 'Two-factor authentication enabled.');
    }

    /** Enable email or SMS one-time codes as the second factor. */
    public function enableOtp(Request $request)
    {
        $this->ensureAllowed();
        $data = $request->validate(['method' => ['required', 'in:email,sms'], 'password' => ['required', 'current_password']]);
        $user = $request->user();
        if ($data['method'] === 'sms' && (! $user->phone || ! $user->phone_verified_at)) {
            return back()->withErrors(['method' => 'Verify your phone number before using SMS codes.']);
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_method' => $data['method'],
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();
        $request->session()->put('two_factor_passed', true);
        AuditLogger::log('2fa_enabled', $user, strtoupper($data['method']).' 2FA enabled');

        return back()->with('recovery_codes', $codes)->with('success', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        if (settings('verification.two_factor') === 'required') {
            return back()->withErrors(['password' => 'Two-factor authentication is required by your administrator.']);
        }
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_method' => null, 'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null])->save();
        AuditLogger::log('2fa_disabled', $request->user(), '2FA disabled');

        return back()->with('success', 'Two-factor authentication disabled.');
    }

    public function regenerateCodes(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        abort_unless($request->user()->hasTwoFactorEnabled(), 400);
        $codes = $this->newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes)])->save();

        return back()->with('recovery_codes', $codes)->with('success', 'New recovery codes generated. Old codes no longer work.');
    }

    protected function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    protected function ensureAllowed(): void
    {
        abort_if(settings('verification.two_factor') === 'off', 403, 'Two-factor authentication is disabled by the administrator.');
    }
}
