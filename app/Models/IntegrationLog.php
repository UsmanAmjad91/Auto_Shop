<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $fillable = ['integration', 'action', 'endpoint', 'status_code', 'successful', 'message', 'duration_ms', 'user_id'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    public static function record(string $integration, string $action, bool $ok, ?string $message = null,
        ?string $endpoint = null, ?int $status = null, ?int $ms = null): self
    {
        return static::create([
            'integration' => $integration, 'action' => $action, 'successful' => $ok,
            'message' => $message ? mb_substr($message, 0, 2000) : null,
            'endpoint' => $endpoint ? preg_replace('/(key|token|secret|apikey)=[^&]+/i', '$1=***', $endpoint) : null,
            'status_code' => $status, 'duration_ms' => $ms, 'user_id' => auth()->id(),
        ]);
    }
}
