<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioSmsProvider implements SmsProvider
{
    public function __construct(protected string $sid, protected string $token, protected string $from) {}

    public function send(string $to, string $message): array
    {
        try {
            $res = Http::asForm()->withBasicAuth($this->sid, $this->token)->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'To' => $to, 'From' => $this->from, 'Body' => $message,
                ]);

            return $res->successful()
                ? ['success' => true, 'message' => 'Sent', 'id' => (string) $res->json('sid')]
                : ['success' => false, 'message' => (string) ($res->json('message') ?? 'HTTP '.$res->status())];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'twilio';
    }
}
