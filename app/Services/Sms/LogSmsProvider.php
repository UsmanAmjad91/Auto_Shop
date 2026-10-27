<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;

/** Development provider: writes messages to the application log instead of sending. */
class LogSmsProvider implements SmsProvider
{
    public function send(string $to, string $message): array
    {
        Log::info("[SMS to {$to}] {$message}");

        return ['success' => true, 'message' => 'Logged (no SMS sent - log provider active).'];
    }

    public function name(): string
    {
        return 'log';
    }
}
