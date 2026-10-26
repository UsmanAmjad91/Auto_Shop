<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'vin', 'stock_number', 'make', 'model', 'year', 'trim', 'engine', 'transmission', 'fuel_type', 'body_type',
        'drive_type', 'exterior_color', 'interior_color', 'mileage', 'license_plate', 'registration_number',
        'registration_expiry', 'registration_state', 'purchase_date', 'purchase_price', 'purchase_source',
        'supplier_id', 'customer_id', 'ownership_type', 'condition', 'location', 'description', 'notes',
        'repair_status', 'inventory_status', 'payment_status', 'assigned_employee_id', 'asking_price',
        'sale_price', 'custom_fields', 'decoded_data', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'registration_expiry' => 'date',
            'sold_at' => 'date',
            'custom_fields' => 'array',
            'decoded_data' => 'array',
            'purchase_price' => 'decimal:2', 'parts_cost' => 'decimal:2', 'labor_cost' => 'decimal:2',
            'interior_cost' => 'decimal:2', 'detailing_cost' => 'decimal:2', 'other_cost' => 'decimal:2',
            'total_investment' => 'decimal:2', 'asking_price' => 'decimal:2', 'sale_price' => 'decimal:2',
            'amount_paid' => 'decimal:2', 'balance_due' => 'decimal:2',
        ];
    }

    public function title(): string
    {
        return trim("{$this->year} {$this->make} {$this->model} {$this->trim}");
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function owner()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'sold_to_customer_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function images()
    {
        return $this->hasMany(VehicleImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(VehicleImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class)->latest();
    }

    public function statusHistory()
    {
        return $this->hasMany(VehicleStatusHistory::class)->latest('id');
    }

    public function expenses()
    {
        return $this->hasMany(VehicleExpense::class)->latest('expense_date');
    }

    public function inspections()
    {
        return $this->hasMany(VehicleInspection::class)->latest('inspection_date');
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class)->latest();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function saleInvoice()
    {
        return $this->belongsTo(Invoice::class, 'sale_invoice_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isCompanyStock(): bool
    {
        return in_array($this->ownership_type, ['company', 'consignment'], true);
    }

    /** Actual profit when sold, otherwise estimated against asking price. */
    public function profit(): ?float
    {
        if ($this->sale_price !== null) {
            return Money::sub($this->sale_price, $this->total_investment);
        }

        return $this->asking_price !== null ? Money::sub($this->asking_price, $this->total_investment) : null;
    }

    public function isProfitEstimated(): bool
    {
        return $this->sale_price === null;
    }

    public function primaryImageUrl(): ?string
    {
        $img = $this->relationLoaded('primaryImage') ? $this->primaryImage : $this->primaryImage()->first();

        return $img ? asset('storage/'.$img->path) : null;
    }

    public function repairStatusModel()
    {
        return $this->belongsTo(VehicleStatus::class, 'repair_status', 'slug');
    }
}
