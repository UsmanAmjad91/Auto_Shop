<?php

use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;

if (! function_exists('settings')) {
    /** Read an application setting ("group.key") with a default. */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service : $service->get($key, $default);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $value): string
    {
        return Money::format($value);
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(mixed $date, bool $withTime = false): string
    {
        if (! $date) {
            return '';
        }
        $date = $date instanceof DateTimeInterface ? $date : Carbon::parse($date);
        $format = settings('general.date_format', 'Y-m-d');

        return $date->format($withTime ? $format.' H:i' : $format);
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return $status ? ucwords(str_replace('_', ' ', $status)) : '';
    }
}

if (! function_exists('status_color')) {
    /** Bootstrap contextual color for any status slug used across modules. */
    function status_color(?string $status): string
    {
        return match ($status) {
            'approved', 'available', 'paid', 'completed', 'present', 'delivered', 'ready_for_dispatch',
            'repair_completed', 'quality_checked', 'received', 'settled', 'active', 'done', 'pass', 'closed' => 'success',
            'pending', 'partial', 'partially_paid', 'low_stock', 'waiting_for_parts', 'scheduled', 'on_hold',
            'late', 'reserved', 'ordered', 'partially_received', 'open', 'quality_inspection', 'draft' => 'warning',
            'rejected', 'suspended', 'unpaid', 'out_of_stock', 'overdue', 'cancelled', 'void', 'absent',
            'disabled', 'damaged', 'fail', 'reversed', 'terminated', 'urgent' => 'danger',
            'under_repair', 'in_progress', 'in_repair', 'under_inspection', 'issued', 'leave' => 'info',
            'sold', 'dispatched', 'refunded', 'holiday', 'returned' => 'primary',
            default => 'secondary',
        };
    }
}
