<?php

namespace App\Contracts;

/**
 * SMS provider interface. Implementations: Log (development), Twilio, Vonage,
 * and a generic HTTP provider. Note: SMS delivery is billed by providers/carriers.
 */
interface SmsProvider
{
    /** @return array{success:bool, message:string, id?:string} */
    public function send(string $to, string $message): array;

    public function name(): string;
}
