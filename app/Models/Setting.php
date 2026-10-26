<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_encrypted'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }
}
