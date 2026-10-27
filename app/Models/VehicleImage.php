<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleImage extends Model
{
    protected $fillable = ['vehicle_id', 'path', 'caption', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
