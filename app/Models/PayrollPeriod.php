<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    use Auditable;

    protected $fillable = ['name', 'frequency', 'start_date', 'end_date', 'pay_date', 'status', 'total_gross', 'total_net',
        'total_paid', 'notes', 'approved_by', 'approved_at', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'pay_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
