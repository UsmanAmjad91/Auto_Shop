<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartCompatibility extends Model
{
    protected $fillable = ['part_id', 'make', 'model', 'year_from', 'year_to'];

    public function label(): string
    {
        $years = $this->year_from || $this->year_to ? ' ('.($this->year_from ?: '…').'–'.($this->year_to ?: '…').')' : '';

        return trim($this->make.' '.$this->model).$years;
    }
}
