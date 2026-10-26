<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartCategory extends Model
{
    protected $fillable = ['name', 'parent_id', 'description'];

    public function parts()
    {
        return $this->hasMany(Part::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(PartCategory::class, 'parent_id');
    }
}
