<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stored on the private "local" disk; served only through an authorised controller. */
class VehicleDocument extends Model
{
    public const TYPES = ['title' => 'Title', 'registration' => 'Registration', 'inspection' => 'Inspection Report',
        'bill_of_sale' => 'Bill of Sale', 'insurance' => 'Insurance', 'warranty' => 'Warranty', 'other' => 'Other'];

    protected $fillable = ['vehicle_id', 'document_type', 'title', 'original_name', 'path', 'mime_type', 'size', 'expires_at', 'uploaded_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
