<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class AuditLogger
{
    /** Attributes never written to the audit trail. */
    public const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'credentials', 'code_hash', 'value', 'updated_at', 'created_at'];

    public static function log(string $event, ?Model $model = null, ?string $description = null,
        array $old = [], array $new = []): void
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => $event,
                'auditable_type' => $model?->getMorphClass(),
                'auditable_id' => $model?->getKey(),
                'description' => $description,
                'old_values' => self::clean($old) ?: null,
                'new_values' => self::clean($new) ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : 'console',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected static function clean(array $values): array
    {
        return array_diff_key($values, array_flip(self::HIDDEN));
    }
}
