<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'part_id', 'quantity_ordered', 'quantity_received', 'unit_cost', 'line_total'];

    public function part()
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
