<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'item_type', 'vehicle_id', 'part_id', 'service_job_id', 'description', 'quantity',
        'unit_price', 'unit_cost', 'taxable', 'line_total', 'stock_deducted', 'sort_order'];

    protected function casts(): array
    {
        return ['taxable' => 'boolean', 'stock_deducted' => 'boolean', 'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function part()
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function serviceJob()
    {
        return $this->belongsTo(ServiceJob::class)->withTrashed();
    }
}
