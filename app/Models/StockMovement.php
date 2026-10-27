<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only stock ledger. */
class StockMovement extends Model
{
    public const TYPES = [
        'purchase' => 'Supplier Purchase', 'add' => 'Stock Added', 'remove' => 'Stock Removed', 'adjust' => 'Adjustment',
        'reserve' => 'Reserved', 'unreserve' => 'Reservation Released', 'job_issue' => 'Issued to Job',
        'job_return' => 'Returned from Job', 'sale' => 'Sold', 'sale_return' => 'Sale Reversed',
        'return_to_supplier' => 'Returned to Supplier', 'damaged' => 'Marked Damaged',
        'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out',
    ];

    protected $fillable = ['part_id', 'location_id', 'type', 'quantity', 'reserved_change', 'balance_after', 'unit_cost',
        'reference_type', 'reference_id', 'vehicle_id', 'notes', 'user_id'];

    public function part()
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
