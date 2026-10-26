<?php

/*
|--------------------------------------------------------------------------
| AutoShop Manager configuration
|--------------------------------------------------------------------------
| "defaults" are fallbacks for the database-backed settings (Settings center).
| "permissions" is the module => actions matrix used to seed Spatie permissions
| ("vehicles.view", "invoices.manage_payments", ...). Add a module here and run
| `php artisan autoshop:sync-permissions` to extend the system.
*/

return [

    'defaults' => [
        'general' => [
            'company_name' => env('APP_NAME', 'AutoShop Manager'),
            'company_email' => null,
            'company_phone' => null,
            'company_address' => null,
            'website' => null,
            'logo' => null,
            'favicon' => null,
            'timezone' => env('APP_TIMEZONE', 'UTC'),
            'date_format' => 'Y-m-d',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'language' => 'en',
            'tax_name' => 'Sales Tax',
            'tax_rate' => 0,
            'invoice_prefix' => 'INV-',
            'stock_prefix' => 'STK-',
            'job_prefix' => 'JOB-',
            'po_prefix' => 'PO-',
            'payment_prefix' => 'PAY-',
            'customer_prefix' => 'CUS-',
            'supplier_prefix' => 'SUP-',
            'employee_prefix' => 'EMP-',
            'part_prefix' => 'PRT-',
            'pagination' => 25,
            'invoice_due_days' => 15,
            'invoice_terms' => 'Payment is due by the due date shown. Thank you for your business.',
            'invoice_template' => 'classic',
        ],
        'theme' => [
            'mode' => 'auto',
            'primary_color' => '#1f6feb',
            'secondary_color' => '#6c757d',
            'sidebar_color' => '#111827',
            'header_color' => '#ffffff',
            'font' => 'Inter',
            'sidebar_collapsed' => false,
            'logo_height' => 34,
            'dashboard_layout' => 'standard',
        ],
        'mail' => [
            'override' => false,
            'host' => null,
            'port' => 587,
            'username' => null,
            'encryption' => 'tls',
            'from_name' => env('APP_NAME', 'AutoShop Manager'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        ],
        'sms' => [
            'provider' => 'log',
            'sender' => null,
            'code_expiry_minutes' => 10,
            'rate_limit_per_hour' => 5,
        ],
        'verification' => [
            'email' => false,
            'sms' => false,
            'two_factor' => 'optional', // off, optional, required
            'admin_approval' => true,
            'login_notifications' => false,
        ],
        'notifications' => [
            'invoice_email' => true,
            'admin_approval_email' => true,
            'low_stock_email' => true,
            'payment_receipt_email' => true,
        ],
        'inventory' => [
            'low_stock_default' => 5,
            'allow_negative' => false,
        ],
        'payroll' => [
            'daily_overtime_threshold' => 8,
            'weekly_overtime_threshold' => 40,
            'overtime_multiplier' => 1.5,
            'late_grace_minutes' => 10,
            'work_start' => '08:00',
            'payment_due_days' => 3,
        ],
        'reminders' => [
            'enabled' => true,
            'days_after_due' => 3,
            'repeat_every_days' => 7,
        ],
        'backup' => [
            'enabled' => false,
            'frequency' => 'daily',
            'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        ],
        'retention' => [
            'audit_logs_days' => 730,
            'login_history_days' => 365,
            'integration_logs_days' => 90,
        ],
        'security' => [
            'session_lifetime' => (int) env('SESSION_LIFETIME', 120),
            'max_login_attempts' => 5,
            'password_min_length' => 8,
        ],
        'maintenance' => [
            'enabled' => false,
            'message' => 'The system is undergoing scheduled maintenance. Please try again shortly.',
        ],
        'vin' => [
            'provider' => 'nhtsa', // nhtsa, alternative
            'nhtsa_url' => env('NHTSA_VPIC_URL', 'https://vpic.nhtsa.dot.gov/api/vehicles'),
            'alt_url' => null,   // e.g. https://api.example.com/vin/{vin}?key={key}
            'timeout' => 15,
        ],
        'payment' => [
            'gateway' => 'none', // none, stripe, square, other
            'public_key' => null,
        ],
    ],

    'permissions' => [
        'dashboard' => ['view'],
        'vehicles' => ['view', 'create', 'edit', 'delete', 'export', 'print', 'import', 'view_costs', 'change_status'],
        'parts' => ['view', 'create', 'edit', 'delete', 'export', 'print', 'import', 'adjust_stock', 'negative_stock'],
        'purchase_orders' => ['view', 'create', 'edit', 'delete', 'approve', 'manage_payments'],
        'suppliers' => ['view', 'create', 'edit', 'delete', 'export'],
        'customers' => ['view', 'create', 'edit', 'delete', 'export'],
        'employees' => ['view', 'create', 'edit', 'delete', 'export', 'view_pay'],
        'attendance' => ['view', 'view_all', 'create', 'edit', 'delete', 'export'],
        'payroll' => ['view', 'create', 'edit', 'delete', 'approve', 'manage_payments', 'export', 'print'],
        'service_jobs' => ['view', 'view_all', 'create', 'edit', 'delete', 'approve', 'export', 'print'],
        'tasks' => ['view', 'view_all', 'create', 'edit', 'delete'],
        'invoices' => ['view', 'create', 'edit', 'delete', 'approve', 'void', 'export', 'print', 'email'],
        'payments' => ['view', 'create', 'refund', 'reverse', 'export', 'manage_payments'],
        'reports' => ['view', 'export'],
        'financials' => ['view'],
        'users' => ['view', 'create', 'edit', 'delete', 'approve'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['manage'],
        'audit_logs' => ['view'],
        'backups' => ['manage'],
    ],

    /*
     * Default roles. "*" = every permission. Entries ending with ".*" grant
     * every action of a module.
     */
    'roles' => [
        'Super Administrator' => ['*'],
        'Administrator' => ['*'],
        'Manager' => [
            'dashboard.*', 'vehicles.*', 'parts.*', 'purchase_orders.*', 'suppliers.*', 'customers.*',
            'employees.view', 'employees.create', 'employees.edit', 'employees.export',
            'attendance.*', 'service_jobs.*', 'tasks.*', 'invoices.*', 'payments.view', 'payments.create',
            'payments.export', 'payments.manage_payments', 'reports.*', 'financials.view', 'users.view', 'audit_logs.view',
        ],
        'Inventory Manager' => [
            'dashboard.view', 'vehicles.*', 'parts.view', 'parts.export', 'suppliers.view', 'customers.view',
            'service_jobs.view', 'service_jobs.view_all', 'tasks.view', 'reports.view',
        ],
        'Repair Technician' => [
            'dashboard.view', 'vehicles.view', 'vehicles.change_status', 'parts.view', 'service_jobs.view',
            'service_jobs.edit', 'tasks.view', 'tasks.edit', 'attendance.view', 'attendance.create',
        ],
        'Parts Manager' => [
            'dashboard.view', 'parts.*', 'purchase_orders.*', 'suppliers.*', 'vehicles.view',
            'service_jobs.view', 'service_jobs.view_all', 'reports.view',
        ],
        'HR / Payroll Manager' => [
            'dashboard.view', 'employees.*', 'attendance.*', 'payroll.*', 'tasks.*', 'reports.view', 'reports.export',
        ],
        'Sales Representative' => [
            'dashboard.view', 'vehicles.view', 'vehicles.print', 'customers.view', 'customers.create',
            'customers.edit', 'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.print',
            'invoices.email', 'payments.view', 'payments.create', 'attendance.view', 'attendance.create',
        ],
        'Accountant' => [
            'dashboard.view', 'vehicles.view', 'vehicles.view_costs', 'vehicles.export', 'parts.view',
            'purchase_orders.view', 'purchase_orders.manage_payments', 'suppliers.view', 'customers.view',
            'customers.export', 'employees.view', 'employees.view_pay', 'payroll.view', 'payroll.manage_payments',
            'payroll.export', 'payroll.print', 'invoices.*', 'payments.*', 'reports.*', 'financials.view',
        ],
        'Employee' => [
            'dashboard.view', 'attendance.view', 'attendance.create', 'tasks.view', 'tasks.edit', 'service_jobs.view',
        ],
        'Viewer' => [
            'dashboard.view', 'vehicles.view', 'parts.view', 'customers.view', 'suppliers.view',
        ],
    ],

    // Roles that may never be deleted or have permissions removed.
    'protected_roles' => ['Super Administrator'],

    'uploads' => [
        'image_max_kb' => 5120,
        'document_max_kb' => 10240,
        'image_mimes' => 'jpg,jpeg,png,webp',
        'document_mimes' => 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt',
    ],

    'enums' => [
        'body_types' => ['SUV', 'Sedan', 'Van', 'Truck', 'Coupe', 'Hatchback', 'Other'],
        'fuel_types' => ['Gasoline', 'Diesel', 'Hybrid', 'Plug-in Hybrid', 'Electric', 'Flex Fuel', 'CNG', 'Other'],
        'transmissions' => ['Automatic', 'Manual', 'CVT', 'Dual-Clutch', 'Other'],
        'ownership_types' => ['company' => 'Company-Owned Vehicle', 'customer' => 'Customer / Client Vehicle', 'consignment' => 'Consignment Vehicle', 'other' => 'Other'],
        'conditions' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'salvage' => 'Salvage'],
        'purchase_sources' => ['auction' => 'Auction', 'trade_in' => 'Trade-in', 'private' => 'Private Seller', 'dealer' => 'Dealer', 'other' => 'Other'],
        'inventory_statuses' => ['available' => 'Available', 'in_repair' => 'In Repair', 'reserved' => 'Reserved', 'sold' => 'Sold', 'delivered' => 'Delivered', 'returned' => 'Returned', 'archived' => 'Archived'],
        'vehicle_payment_statuses' => ['unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'pending' => 'Payment Pending', 'refunded' => 'Refunded'],
        'expense_categories' => ['transport' => 'Transport', 'registration' => 'Registration', 'auction_fee' => 'Auction Fee', 'inspection' => 'Inspection', 'fuel' => 'Fuel', 'advertising' => 'Advertising', 'other' => 'Other'],
        'part_conditions' => ['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'],
        'pay_types' => ['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly', 'fixed' => 'Fixed Salary'],
        'attendance_statuses' => ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'leave' => 'Leave', 'holiday' => 'Holiday'],
        'employment_statuses' => ['active' => 'Active', 'on_leave' => 'On Leave', 'suspended' => 'Suspended', 'terminated' => 'Terminated'],
        'service_categories' => ['repair' => 'Repair Jobs', 'interior' => 'Interior Services', 'cleaning' => 'Washing & Detailing'],
        'service_statuses' => ['pending' => 'Pending', 'scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'quality_checked' => 'Quality Checked', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'],
        'invoice_types' => ['vehicle_sale' => 'Vehicle Sale', 'repair' => 'Vehicle Repair', 'parts_sale' => 'Parts Sale', 'interior' => 'Interior Repair', 'detailing' => 'Vehicle Detailing', 'combined' => 'Combined Invoice'],
        'invoice_statuses' => ['draft' => 'Draft', 'issued' => 'Issued', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'void' => 'Void', 'refunded' => 'Refunded'],
        'invoice_item_types' => ['vehicle' => 'Vehicle', 'part' => 'Part', 'labor' => 'Labor', 'interior' => 'Interior Repair', 'detailing' => 'Wash / Detailing', 'service_job' => 'Service Job', 'other' => 'Other'],
        'payment_methods' => ['cash' => 'Cash', 'credit_card' => 'Credit Card', 'debit_card' => 'Debit Card', 'bank_transfer' => 'Bank Transfer', 'check' => 'Check', 'financing' => 'Financing / Loan', 'other' => 'Other'],
        'payment_types' => ['payment' => 'Payment', 'deposit' => 'Deposit / Down Payment', 'refund' => 'Refund', 'financing_settlement' => 'Lender Settlement'],
        'priorities' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
        'user_statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'disabled' => 'Disabled'],
    ],

    'service_checklists' => [
        'repair' => ['Initial diagnosis recorded', 'Customer approval obtained', 'Parts verified', 'Repair performed', 'Road test', 'Final inspection'],
        'interior' => ['Damage documented with photos', 'Materials confirmed', 'Work completed', 'Fit & finish checked'],
        'cleaning' => ['Exterior washed', 'Interior vacuumed', 'Windows cleaned', 'Final walk-around'],
    ],

    'inspection_checklist' => [
        'Exterior body & paint', 'Glass & mirrors', 'Lights & signals', 'Tires & wheels', 'Brakes',
        'Suspension & steering', 'Engine & fluids', 'Battery & charging', 'Transmission', 'Exhaust',
        'Interior & seats', 'Dashboard & electronics', 'A/C & heating', 'Safety belts & airbags', 'Road test',
    ],
];
