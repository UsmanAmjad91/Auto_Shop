<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartStock extends Model
{
    protected $fillable = ['part_id', 'location_id', 'quantity', 'shelf', 'bin'];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }
}
