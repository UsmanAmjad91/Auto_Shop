<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lender financing for a vehicle sale. The financed amount is a receivable from
 * the lender and is NOT counted as cash until a settlement payment is recorded.
 */
class FinancingRecord extends Model
{
    use Auditable;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'settled' => 'Settled', 'cancelled' => 'Cancelled'];

    protected $fillable = ['invoice_id', 'lender_name', 'lender_contact', 'loan_reference', 'financed_amount', 'down_payment',
        'interest_rate', 'term_months', 'status', 'approved_date', 'settled_date', 'settlement_payment_id', 'notes'];

    protected function casts(): array
    {
        return ['approved_date' => 'date', 'settled_date' => 'date', 'financed_amount' => 'decimal:2', 'down_payment' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function settlementPayment()
    {
        return $this->belongsTo(Payment::class, 'settlement_payment_id');
    }
}
