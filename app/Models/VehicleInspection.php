<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleInspection extends Model
{
    protected $fillable = ['vehicle_id', 'inspector_id', 'inspection_date', 'type', 'items', 'result', 'notes'];

    protected function casts(): array
    {
        return ['inspection_date' => 'date', 'items' => 'array'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function inspector()
    {
        return $this->belongsTo(Employee::class, 'inspector_id');
    }
}
