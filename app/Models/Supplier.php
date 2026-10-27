<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'contact_person', 'email', 'phone', 'address', 'city', 'country',
        'website', 'tax_number', 'payment_terms_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parts()
    {
        return $this->hasMany(Part::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function totalPurchased(): float
    {
        return Money::sum($this->purchaseOrders()->where('status', '!=', 'cancelled')->pluck('total'));
    }

    public function totalPaid(): float
    {
        return Money::sum($this->payments()->pluck('amount'));
    }

    public function amountPending(): float
    {
        return Money::sum($this->purchaseOrders()->where('status', '!=', 'cancelled')->pluck('balance'));
    }
}
