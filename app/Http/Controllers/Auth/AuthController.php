<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserApprovalRequest;
use App\Notifications\LoginAlert;
use App\Notifications\NewUserRegistered;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            LoginHistory::create(['user_id' => $user?->id, 'email' => $credentials['email'], 'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500), 'successful' => false, 'reason' => 'invalid_credentials']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::user();
        if (in_array($user->status, ['rejected', 'suspended', 'disabled'], true)) {
            Auth::logout();
            LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500), 'successful' => false, 'reason' => 'account_'.$user->status]);
            throw ValidationException::withMessages(['email' => 'Your account is '.$user->status.'. Contact an administrator.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('two_factor_passed');

        $history = LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500), 'successful' => true, 'logged_in_at' => now()]);
        $request->session()->put('login_history_id', $history->id);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        if (settings()->bool('verification.login_notifications')) {
            $user->notify(new LoginAlert($request->ip(), substr((string) $request->userAgent(), 0, 200), now()->toDayDateTimeString()));
        }

        if ($user->hasTwoFactorEnabled() && settings('verification.two_factor') !== 'off') {
            if ($user->two_factor_method !== 'totp') {
                app(OtpService::class)->send($user, $user->two_factor_method, 'two_factor');
            }

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => [settings()->bool('verification.sms') ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $needsApproval = settings()->bool('verification.admin_approval', true);

        $user = DB::transaction(function () use ($data, $needsApproval) {
            $user = User::create($data + ['status' => $needsApproval ? 'pending' : 'approved']);
            if ($needsApproval) {
                UserApprovalRequest::create(['user_id' => $user->id, 'status' => 'pending']);
            } else {
                $user->assignRole('Employee');
                $user->forceFill(['approved_at' => now()])->saveQuietly();
            }

            return $user;
        });

        if (settings()->bool('verification.email')) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($needsApproval) {
            try {
                $admins = User::permission('users.approve')->where('status', 'approved')->get();
                $admins = $admins->merge(User::role('Super Administrator')->where('status', 'approved')->get())->unique('id');
                Notification::send($admins, new NewUserRegistered($user));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        AuditLogger::log('registered', $user, "{$user->email} registered");
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($needsApproval ? 'account.pending' : 'dashboard');
    }

    public function pending(Request $request)
    {
        if ($request->user()->isApproved()) {
            return redirect()->route('dashboard');
        }

        return view('auth.pending', ['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        if ($id = $request->session()->get('login_history_id')) {
            LoginHistory::whereKey($id)->update(['logged_out_at' => now()]);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
