<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = ['code', 'type', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'alt_phone',
        'address', 'city', 'state', 'postal_code', 'country', 'tax_number', 'driver_license', 'notes', 'is_active', 'created_by'];

    protected $appends = ['display_name'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);

        return $this->company_name ? ($this->type === 'business' ? $this->company_name : "$name ({$this->company_name})") : $name;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->postal_code, $this->country])->filter()->implode(', ');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function purchasedVehicles()
    {
        return $this->hasMany(Vehicle::class, 'sold_to_customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class);
    }

    /** Amount the customer owes on open invoices (excludes pending lender financing). */
    public function outstandingBalance(): float
    {
        $balance = Money::sum($this->invoices()->open()->pluck('balance'));
        $financing = Money::sum(FinancingRecord::whereIn('invoice_id', $this->invoices()->open()->select('id'))
            ->whereIn('status', ['pending', 'approved'])->pluck('financed_amount'));

        return max(0, Money::sub($balance, $financing));
    }
}
