<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Part extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    /** quantity / quantity_reserved are changed ONLY through InventoryService. */
    protected $fillable = ['part_number', 'name', 'oem_number', 'sku', 'barcode', 'category_id', 'manufacturer', 'brand',
        'description', 'purchase_cost', 'selling_price', 'min_stock_level', 'unit', 'default_location_id', 'shelf', 'bin',
        'supplier_id', 'purchase_date', 'purchase_invoice', 'warranty_months', 'warranty_terms', 'supervisor_id',
        'condition', 'allow_negative_stock', 'notes'];

    protected $appends = ['quantity_available'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'allow_negative_stock' => 'boolean',
            'purchase_cost' => 'decimal:2', 'selling_price' => 'decimal:2'];
    }

    public function getQuantityAvailableAttribute(): int
    {
        return (int) $this->quantity - (int) $this->quantity_reserved;
    }

    public function category()
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function defaultLocation()
    {
        return $this->belongsTo(StockLocation::class, 'default_location_id');
    }

    public function stocks()
    {
        return $this->hasMany(PartStock::class);
    }

    public function images()
    {
        return $this->hasMany(PartImage::class)->orderByDesc('is_primary');
    }

    public function primaryImage()
    {
        return $this->hasOne(PartImage::class)->orderByDesc('is_primary');
    }

    public function compatibilities()
    {
        return $this->hasMany(PartCompatibility::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function stockValue(): float
    {
        return Money::mul($this->purchase_cost, $this->quantity);
    }

    public function computeStockStatus(): string
    {
        $available = $this->quantity_available;
        if ($available <= 0) {
            return 'out_of_stock';
        }

        return $available <= (int) $this->min_stock_level ? 'low_stock' : 'available';
    }

    public function warrantyExpiresOn(): ?Carbon
    {
        return $this->warranty_months && $this->purchase_date ? $this->purchase_date->copy()->addMonths($this->warranty_months) : null;
    }

    public function scopeLowStock($q)
    {
        return $q->whereIn('stock_status', ['low_stock', 'out_of_stock']);
    }
}
