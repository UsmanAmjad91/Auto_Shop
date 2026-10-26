<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    protected $fillable = ['name', 'address', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function default(): self
    {
        return static::where('is_default', true)->first()
            ?? static::first()
            ?? static::create(['name' => 'Main Warehouse', 'is_default' => true]);
    }
}
