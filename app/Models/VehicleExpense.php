<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class VehicleExpense extends Model
{
    use Auditable;

    protected $fillable = ['vehicle_id', 'category', 'description', 'amount', 'expense_date', 'supplier_id', 'reference', 'created_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
