<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    public const EVENTS = [
        'user_registered' => 'New user registration awaiting approval',
        'low_stock' => 'Low stock alerts',
        'invoice_overdue' => 'Overdue invoices',
        'payment_received' => 'Payment received',
        'job_assigned' => 'Service job / task assigned to me',
        'job_status' => 'Service job status changes',
    ];

    protected $fillable = ['user_id', 'event', 'via_database', 'via_mail', 'via_sms'];

    protected function casts(): array
    {
        return ['via_database' => 'boolean', 'via_mail' => 'boolean', 'via_sms' => 'boolean'];
    }
}
