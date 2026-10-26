<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\RateLimiter;

class SmsManager
{
    public function provider(): SmsProvider
    {
        $s = settings();

        return match ($s->get('sms.provider', 'log')) {
            'twilio' => new TwilioSmsProvider(
                (string) ($s->get('sms.api_key') ?: config('services.twilio.sid')),
                (string) ($s->get('sms.api_secret') ?: config('services.twilio.token')),
                (string) ($s->get('sms.sender') ?: config('services.twilio.from')),
            ),
            'vonage' => new VonageSmsProvider((string) $s->get('sms.api_key'), (string) $s->get('sms.api_secret'), (string) $s->get('sms.sender')),
            'http' => new HttpSmsProvider((string) $s->get('sms.http_url'), (string) $s->get('sms.api_key'), $s->get('sms.sender')),
            default => new LogSmsProvider,
        };
    }

    /** Sends with a per-recipient hourly rate limit. */
    public function send(string $to, string $message): array
    {
        $key = 'sms:'.preg_replace('/\D/', '', $to);
        $limit = max(1, (int) settings('sms.rate_limit_per_hour', 5));
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return ['success' => false, 'message' => 'Too many SMS sent to this number. Try again later.'];
        }
        RateLimiter::hit($key, 3600);

        $provider = $this->provider();
        $result = $provider->send($to, $message);
        IntegrationLog::record('sms_'.$provider->name(), 'send', $result['success'], $result['message']);

        return $result;
    }
}
