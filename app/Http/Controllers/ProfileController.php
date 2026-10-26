<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Services\AuditLogger;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('notificationSettings', 'employee');

        return view('profile.edit', ['user' => $user, 'events' => NotificationSetting::EVENTS]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'avatar' => Uploads::imageRule(),
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = Uploads::image($request->file('avatar'), 'avatars');
        }
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        if (($data['phone'] ?? null) !== $user->phone) {
            $user->phone_verified_at = null;
        }
        $user->fill(collect($data)->only(['name', 'email', 'phone'])->all())->save();

        if (! $user->hasVerifiedEmail() && settings()->bool('verification.email')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => $request->password])->save();
        // Sign out every other session after a password change.
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        AuditLogger::log('password_changed', $request->user(), 'Password changed');

        return back()->with('success', 'Password changed. Other sessions were signed out.');
    }

    public function preferences(Request $request)
    {
        $request->validate([
            'theme' => ['required', 'in:light,dark,auto'],
            'notifications' => ['array'],
            'notifications.*.via_database' => ['nullable', 'boolean'],
            'notifications.*.via_mail' => ['nullable', 'boolean'],
            'notifications.*.via_sms' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $user->forceFill(['theme' => $request->theme])->save();

        if ($request->boolean('save_notifications')) {
            foreach (array_keys(NotificationSetting::EVENTS) as $event) {
                $row = $request->input("notifications.$event", []);
                NotificationSetting::updateOrCreate(['user_id' => $user->id, 'event' => $event], [
                    'via_database' => (bool) ($row['via_database'] ?? false),
                    'via_mail' => (bool) ($row['via_mail'] ?? false),
                    'via_sms' => (bool) ($row['via_sms'] ?? false),
                ]);
            }
        }

        return $this->done($request, 'Preferences saved.');
    }

    public function security(Request $request, Google2FA $google2fa)
    {
        $user = $request->user();
        $qr = null;
        $secret = $request->session()->get('2fa_pending_secret');
        if ($secret) {
            $url = $google2fa->getQRCodeUrl(settings('general.company_name', 'AutoShop'), $user->email, $secret);
            $qr = (string) QrCode::format('svg')->size(200)->margin(1)->generate($url);
        }

        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id, 'ip' => $s->ip_address, 'agent' => $s->user_agent,
                'last_activity' => Carbon::createFromTimestamp($s->last_activity),
                'current' => $s->id === $request->session()->getId(),
            ]);

        return view('profile.security', [
            'user' => $user,
            'qr' => $qr,
            'secret' => $secret,
            'sessions' => $sessions,
            'logins' => $user->loginHistories()->limit(20)->get(),
            'tokens' => $user->tokens()->latest()->get(),
            'mode' => settings('verification.two_factor', 'optional'),
        ]);
    }

    public function destroySession(Request $request, string $session)
    {
        abort_if($session === $request->session()->getId(), 422, 'Use Logout to end your current session.');
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->delete();

        return back()->with('success', 'Session signed out.');
    }

    public function destroyOtherSessions(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        Auth::logoutOtherDevices($request->password);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', 'All other sessions have been signed out.');
    }

    public function createToken(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);
        $token = $request->user()->createToken($request->name, ['*'], now()->addYear());
        AuditLogger::log('api_token_created', $request->user(), 'API token created: '.$request->name);

        return back()->with('new_token', $token->plainTextToken)->with('success', 'Token created. Copy it now — it will not be shown again.');
    }

    public function destroyToken(Request $request, int $token)
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('success', 'Token revoked.');
    }
}
