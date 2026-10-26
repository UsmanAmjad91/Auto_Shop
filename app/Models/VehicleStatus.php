<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VehicleStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'sort_order', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('vehicle_statuses'));
        static::deleted(fn () => Cache::forget('vehicle_statuses'));
    }

    /** slug => name map of active statuses, in order. */
    public static function options(): array
    {
        return Cache::remember('vehicle_statuses', 600, fn () => static::where('is_active', true)
            ->orderBy('sort_order')->pluck('name', 'slug')->all());
    }

    public static function colorFor(?string $slug): string
    {
        return static::where('slug', $slug)->value('color') ?? status_color($slug);
    }
}
