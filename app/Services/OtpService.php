<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes over email (free / low cost) or SMS (billed by provider).
 * Codes are hashed, expire, are single-use and allow at most 5 attempts.
 */
class OtpService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(protected SmsManager $sms) {}

    public function send(User $user, string $channel, string $purpose): void
    {
        $key = "otp-send:{$user->id}:{$purpose}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many codes requested. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 900);

        $code = (string) random_int(100000, 999999);
        $minutes = (int) settings('sms.code_expiry_minutes', 10);

        OtpCode::where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::create([
            'user_id' => $user->id, 'channel' => $channel, 'purpose' => $purpose,
            'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes($minutes),
        ]);

        $text = settings('general.company_name').": your verification code is {$code}. It expires in {$minutes} minutes.";
        if ($channel === 'sms') {
            if (! $user->phone) {
                throw ValidationException::withMessages(['code' => 'No phone number on your account.']);
            }
            $result = $this->sms->send($user->phone, $text);
            if (! $result['success']) {
                throw ValidationException::withMessages(['code' => 'SMS could not be sent: '.$result['message']]);
            }
        } else {
            $user->notify(new OtpCodeNotification($code, $minutes));
        }
    }

    public function verify(User $user, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('user_id', $user->id)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The code has expired. Request a new one.']);
        }
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'Incorrect code.']);
        }
        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
