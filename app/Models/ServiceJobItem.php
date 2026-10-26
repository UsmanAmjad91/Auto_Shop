<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceJobItem extends Model
{
    public const TYPES = ['service' => 'Service', 'labor' => 'Labor', 'part' => 'Part', 'material' => 'Material'];

    protected $fillable = ['service_job_id', 'item_type', 'service_type_id', 'part_id', 'description', 'quantity',
        'unit_cost', 'unit_price', 'total_cost', 'total_price', 'employee_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'unit_price' => 'decimal:2',
            'total_cost' => 'decimal:2', 'total_price' => 'decimal:2'];
    }

    public function job()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function part()
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
