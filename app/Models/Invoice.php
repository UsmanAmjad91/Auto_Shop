<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /** Totals, amount_paid, balance and statuses are maintained by InvoiceService. */
    protected $fillable = ['invoice_number', 'type', 'customer_id', 'vehicle_id', 'sales_rep_id', 'invoice_date', 'due_date',
        'discount_type', 'discount_value', 'tax_rate', 'customer_name', 'customer_email', 'customer_phone',
        'customer_address', 'notes', 'terms', 'created_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'voided_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2',
            'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2', 'cost_total' => 'decimal:2'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function salesRep()
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function financing()
    {
        return $this->hasOne(FinancingRecord::class)->latestOfMany();
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['draft', 'void', 'paid', 'refunded'], true)
            && $this->due_date && $this->due_date->isPast() && (float) $this->balance > 0;
    }

    /** Pending lender amount (not yet received as cash). */
    public function pendingFinancedAmount(): float
    {
        $f = $this->financing;

        return $f && in_array($f->status, ['pending', 'approved'], true) ? (float) $f->financed_amount : 0.0;
    }

    public function customerBalance(): float
    {
        return max(0, round((float) $this->balance - $this->pendingFinancedAmount(), 2));
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', ['draft', 'void', 'paid', 'refunded'])->where('balance', '>', 0);
    }
}
