<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use Auditable, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partially_received' => 'Partially Received',
        'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $fillable = ['po_number', 'supplier_id', 'location_id', 'order_date', 'expected_date', 'received_date', 'status',
        'supplier_invoice_number', 'subtotal', 'tax_amount', 'shipping_cost', 'total', 'amount_paid', 'balance',
        'payment_status', 'due_date', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'received_date' => 'date', 'due_date' => 'date'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
