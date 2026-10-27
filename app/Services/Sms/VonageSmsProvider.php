<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

class VonageSmsProvider implements SmsProvider
{
    public function __construct(protected string $key, protected string $secret, protected string $from) {}

    public function send(string $to, string $message): array
    {
        try {
            $res = Http::asForm()->timeout(15)->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->key, 'api_secret' => $this->secret, 'from' => $this->from,
                'to' => ltrim($to, '+'), 'text' => $message,
            ]);
            $status = (string) $res->json('messages.0.status');

            return $status === '0'
                ? ['success' => true, 'message' => 'Sent', 'id' => (string) $res->json('messages.0.message-id')]
                : ['success' => false, 'message' => (string) ($res->json('messages.0.error-text') ?? 'HTTP '.$res->status())];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'vonage';
    }
}
