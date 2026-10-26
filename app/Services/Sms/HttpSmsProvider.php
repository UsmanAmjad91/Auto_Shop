<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic provider: POSTs JSON {to, from, message} to a configured URL with a
 * bearer token. Adapt to any gateway that accepts a simple JSON request.
 */
class HttpSmsProvider implements SmsProvider
{
    public function __construct(protected string $url, protected string $token, protected ?string $from) {}

    public function send(string $to, string $message): array
    {
        try {
            $res = Http::withToken($this->token)->timeout(15)->post($this->url, ['to' => $to, 'from' => $this->from, 'message' => $message]);

            return ['success' => $res->successful(), 'message' => $res->successful() ? 'Sent' : 'HTTP '.$res->status()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'http';
    }
}
