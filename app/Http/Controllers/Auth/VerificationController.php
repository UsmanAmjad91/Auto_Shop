<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $user = $request->user();
        abort_unless(hash_equals((string) $user->getKey(), $id) && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('dashboard')->with('success', 'Email verified.');
    }

    public function resend(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'A new verification link has been sent to your email address.');
    }

    public function phoneNotice(Request $request)
    {
        return $request->user()->phone_verified_at ? redirect()->route('dashboard') : view('auth.verify-phone');
    }

    public function phoneSend(Request $request, OtpService $otp)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/']]);
        $user = $request->user();
        if ($user->phone !== $data['phone']) {
            $user->forceFill(['phone' => $data['phone'], 'phone_verified_at' => null])->save();
        }
        $otp->send($user, 'sms', 'phone_verification');

        return back()->with('success', 'Verification code sent by SMS.');
    }

    public function phoneVerify(Request $request, OtpService $otp)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $otp->verify($request->user(), 'phone_verification', $request->code);
        $request->user()->forceFill(['phone_verified_at' => now()])->save();

        return redirect()->route('dashboard')->with('success', 'Phone number verified.');
    }
}
