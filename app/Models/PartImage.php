<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartImage extends Model
{
    protected $fillable = ['part_id', 'path', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
