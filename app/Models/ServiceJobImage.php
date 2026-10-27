<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceJobImage extends Model
{
    protected $fillable = ['service_job_id', 'stage', 'path', 'caption'];

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
