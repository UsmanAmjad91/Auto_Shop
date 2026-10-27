<?php

namespace App\Providers;

use App\Services\SettingsService;
use App\Services\Sms\SmsManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Shared hosting: always build links from APP_URL (no "/public" in URLs, https behind proxies).
        if ($this->app->isProduction() && config('app.url')) {
            URL::forceRootUrl(rtrim(config('app.url'), '/'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // utf8mb4 indexes stay within key-length limits on older MySQL / MariaDB servers.
        Schema::defaultStringLength(191);

        // Portable inclusive date range on DATE columns (works on MySQL and SQLite).
        $between = function (string $column, array $range) {
            [$from, $to] = array_map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string) $d, 0, 10), $range);

            return $this->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
        };
        Builder::macro('whereDateBetween', $between);

        // Super Administrator implicitly holds every permission.
        Gate::before(fn ($user) => $user->hasRole('Super Administrator') ? true : null);

        $this->applyRuntimeSettings();

        Password::defaults(fn () => Password::min((int) settings('security.password_min_length', 8))
            ->letters()->mixedCase()->numbers()
            ->when($this->app->isProduction(), fn ($rule) => $rule->uncompromised()));

        RateLimiter::for('login', function (Request $request) {
            $max = max(3, (int) settings('security.max_login_attempts', 5));

            return [
                Limit::perMinute($max)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perMinute($max * 4)->by($request->ip()),
            ];
        });
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('vin', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->count() : 0);
        });
    }

    /** Apply DB-backed settings (timezone, mail server, session lifetime) at runtime. */
    protected function applyRuntimeSettings(): void
    {
        try {
            $s = $this->app->make(SettingsService::class);

            if ($tz = $s->get('general.timezone')) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
            }
            config(['app.name' => $s->get('general.company_name', config('app.name'))]);
            config(['session.lifetime' => (int) $s->get('security.session_lifetime', config('session.lifetime'))]);

            if ($s->bool('mail.override') && $s->get('mail.host')) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $s->get('mail.host'),
                    'mail.mailers.smtp.port' => (int) $s->get('mail.port', 587),
                    'mail.mailers.smtp.username' => $s->get('mail.username'),
                    'mail.mailers.smtp.password' => $s->get('mail.password'),
                    'mail.mailers.smtp.scheme' => $s->get('mail.encryption') === 'ssl' ? 'smtps' : null,
                    'mail.from.address' => $s->get('mail.from_address'),
                    'mail.from.name' => $s->get('mail.from_name'),
                ]);
            }
        } catch (Throwable) {
            // Database not migrated yet (fresh install) - keep .env configuration.
        }
    }
}
