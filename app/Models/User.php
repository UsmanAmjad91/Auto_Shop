<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status', 'avatar', 'theme', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'preferences' => 'array',
        ];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class)->latest();
    }

    public function approvalRequests()
    {
        return $this->hasMany(UserApprovalRequest::class)->latest();
    }

    public function notificationSettings()
    {
        return $this->hasMany(NotificationSetting::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Administrator');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_method !== null;
    }

    /** Whether this user wants the given event via the given channel (defaults on). */
    public function wantsNotification(string $event, string $channel): bool
    {
        $pref = $this->notificationSettings->firstWhere('event', $event);

        return $pref ? (bool) $pref->{'via_'.$channel} : $channel !== 'sms';
    }

    public function avatarUrl(): string
    {
        return $this->avatar
            ? asset('storage/'.$this->avatar)
            : 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#1f6feb"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="26" fill="#fff">'.e(strtoupper(mb_substr($this->name, 0, 1))).'</text></svg>');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
