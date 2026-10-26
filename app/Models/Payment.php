<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Auditable;

    protected $fillable = ['payment_number', 'customer_id', 'type', 'payment_date', 'amount', 'method', 'reference', 'status',
        'refund_of_payment_id', 'notes', 'received_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'reversed_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function refundOf()
    {
        return $this->belongsTo(Payment::class, 'refund_of_payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(Payment::class, 'refund_of_payment_id');
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    /** Signed effect on invoice balances (refunds reduce amount paid). */
    public function signedAmount(): float
    {
        return $this->isRefund() ? -1 * (float) $this->amount : (float) $this->amount;
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }
}
