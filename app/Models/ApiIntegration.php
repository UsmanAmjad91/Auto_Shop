<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ApiIntegration extends Model
{
    use Auditable;

    protected $fillable = ['type', 'name', 'provider', 'is_active', 'is_default', 'credentials', 'config',
        'last_tested_at', 'last_test_status'];

    /** Secrets never leave the server. */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /** Masked view of credential keys for the UI. */
    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])->map(fn ($v) => $v ? '••••'.substr((string) $v, -4) : '')->all();
    }

    public static function activeFor(string $type): ?self
    {
        return static::where('type', $type)->where('is_active', true)->orderByDesc('is_default')->first();
    }
}
