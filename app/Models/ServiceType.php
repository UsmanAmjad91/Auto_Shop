<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $fillable = ['category', 'name', 'default_price', 'default_cost', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
