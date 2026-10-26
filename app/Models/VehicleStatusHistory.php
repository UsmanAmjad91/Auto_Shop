<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleStatusHistory extends Model
{
    protected $fillable = ['vehicle_id', 'field', 'from_status', 'to_status', 'notes', 'employee_id', 'changed_by'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
