<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Persistent key/value settings with caching. Keys are "group.name".
 * Secret values (passwords, API keys) are encrypted at rest and never
 * returned by all() unless explicitly requested.
 */
class SettingsService
{
    public const CACHE_KEY = 'app_settings_v1';

    /** Keys that must always be encrypted. */
    public const SECRET_KEYS = [
        'mail.password', 'sms.api_key', 'sms.api_secret', 'payment.secret_key', 'vin.alt_api_key',
    ];

    protected ?array $loaded = null;

    public function defaults(): array
    {
        return config('autoshop.defaults', []);
    }

    protected function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return $this->loaded = [];
            }
            $this->loaded = Cache::rememberForever(self::CACHE_KEY, function () {
                return Setting::query()->get(['key', 'value', 'is_encrypted'])
                    ->mapWithKeys(fn ($s) => [$s->key => ['v' => $s->value, 'e' => (bool) $s->is_encrypted]])
                    ->all();
            });
        } catch (Throwable) {
            $this->loaded = [];
        }

        return $this->loaded;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->load();

        if (array_key_exists($key, $all)) {
            $raw = $all[$key]['v'];
            if ($all[$key]['e'] && $raw !== null && $raw !== '') {
                try {
                    $raw = Crypt::decryptString($raw);
                } catch (Throwable) {
                    return $default;
                }
            }

            return $this->cast($raw);
        }

        return Arr::get($this->defaults(), $key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function set(string $key, mixed $value): void
    {
        $encrypt = in_array($key, self::SECRET_KEYS, true);
        $stored = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : $value);

        if ($encrypt && $stored !== null && $stored !== '') {
            $stored = Crypt::encryptString((string) $stored);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            ['group' => explode('.', $key)[0], 'value' => $stored, 'is_encrypted' => $encrypt]
        );
        $this->flush();
    }

    public function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v);
        }
    }

    public function hasSecret(string $key): bool
    {
        $all = $this->load();

        return isset($all[$key]) && $all[$key]['v'] !== null && $all[$key]['v'] !== '';
    }

    public function group(string $group): array
    {
        $out = Arr::get($this->defaults(), $group, []);
        foreach ($this->load() as $key => $_) {
            if (str_starts_with($key, $group.'.') && ! in_array($key, self::SECRET_KEYS, true)) {
                Arr::set($out, substr($key, strlen($group) + 1), $this->get($key));
            }
        }

        return $out;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    protected function cast(mixed $raw): mixed
    {
        if (! is_string($raw)) {
            return $raw;
        }
        if ($raw !== '' && ($raw[0] === '[' || $raw[0] === '{')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $raw;
    }
}
