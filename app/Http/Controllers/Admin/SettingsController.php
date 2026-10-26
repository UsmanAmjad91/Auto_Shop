<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegration;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\IntegrationLog;
use App\Models\PartCategory;
use App\Models\PartStock;
use App\Models\ServiceType;
use App\Models\StockLocation;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\SettingsService;
use App\Services\Sms\SmsManager;
use App\Services\VinDecoderService;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin-only settings center. Simple key/value sections are described by a
 * field schema (used for both the form and validation); richer sections
 * (integrations, statuses, lookups, custom fields, backups) have their own actions.
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings) {}

    public static function sections(): array
    {
        return [
            'general' => ['title' => 'General', 'icon' => 'building', 'desc' => 'Company profile, localisation, numbering, taxes'],
            'theme' => ['title' => 'Theme', 'icon' => 'palette', 'desc' => 'Colours, fonts, layout and dark mode'],
            'email' => ['title' => 'Email', 'icon' => 'envelope', 'desc' => 'SMTP server and email notifications'],
            'sms' => ['title' => 'SMS', 'icon' => 'phone', 'desc' => 'SMS provider, codes and rate limits'],
            'verification' => ['title' => 'Verification', 'icon' => 'shield-check', 'desc' => 'Email / SMS verification, 2FA, approvals'],
            'integrations' => ['title' => 'API Integrations', 'icon' => 'plug', 'desc' => 'VIN decoders, payment gateway, other APIs'],
            'operations' => ['title' => 'Operations', 'icon' => 'sliders', 'desc' => 'Stock thresholds, payroll rules, reminders, retention'],
            'security' => ['title' => 'Security & Maintenance', 'icon' => 'lock', 'desc' => 'Sessions, login limits, maintenance mode'],
            'statuses' => ['title' => 'Repair Statuses', 'icon' => 'list-ol', 'desc' => 'Customise vehicle repair statuses'],
            'lookups' => ['title' => 'Lookups', 'icon' => 'tags', 'desc' => 'Departments, part categories, locations, service types'],
            'custom-fields' => ['title' => 'Custom Vehicle Fields', 'icon' => 'input-cursor-text', 'desc' => 'Add your own vehicle fields'],
            'backups' => ['title' => 'Backups', 'icon' => 'database', 'desc' => 'Database backups and restore procedure'],
            'integration-logs' => ['title' => 'Integration Logs', 'icon' => 'journal-text', 'desc' => 'API call history'],
        ];
    }

    /** Field schema: key => [label, type, rules, options?, help?] */
    public static function schema(string $section): array
    {
        $tz = collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($t) => [$t => $t])->all();
        $bool = ['nullable', 'boolean'];

        return match ($section) {
            'general' => [
                'general.company_name' => ['Company Name', 'text', ['required', 'string', 'max:120']],
                'general.logo' => ['Company Logo', 'image', Uploads::imageRule()],
                'general.favicon' => ['Favicon', 'image', ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,webp', 'max:512']],
                'general.company_email' => ['Company Email', 'email', ['nullable', 'email', 'max:255']],
                'general.company_phone' => ['Company Phone', 'text', ['nullable', 'string', 'max:40']],
                'general.company_address' => ['Company Address', 'textarea', ['nullable', 'string', 'max:500']],
                'general.website' => ['Website', 'url', ['nullable', 'url', 'max:255']],
                'general.timezone' => ['Time Zone', 'select', ['required', Rule::in(array_keys($tz))], $tz],
                'general.date_format' => ['Date Format', 'select', ['required', 'in:Y-m-d,m/d/Y,d/m/Y,d.m.Y,M j\, Y'],
                    ['Y-m-d' => '2026-12-31', 'm/d/Y' => '12/31/2026', 'd/m/Y' => '31/12/2026', 'd.m.Y' => '31.12.2026', 'M j, Y' => 'Dec 31, 2026']],
                'general.currency' => ['Currency Code', 'text', ['required', 'string', 'size:3']],
                'general.currency_symbol' => ['Currency Symbol', 'text', ['required', 'string', 'max:5']],
                'general.language' => ['Language', 'select', ['required', 'in:en'], ['en' => 'English']],
                'general.tax_name' => ['Tax Name', 'text', ['required', 'string', 'max:40']],
                'general.tax_rate' => ['Default Tax Rate (%)', 'number', ['required', 'numeric', 'min:0', 'max:100']],
                'general.invoice_prefix' => ['Invoice Prefix', 'text', ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]*$/']],
                'general.stock_prefix' => ['Stock Number Prefix', 'text', ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/]*$/']],
                'general.job_prefix' => ['Service Job Prefix', 'text', ['required', 'string', 'max:10']],
                'general.po_prefix' => ['Purchase Order Prefix', 'text', ['required', 'string', 'max:10']],
                'general.payment_prefix' => ['Payment Prefix', 'text', ['required', 'string', 'max:10']],
                'general.pagination' => ['Default Rows per Page', 'select', ['required', 'in:10,25,50,100'], [10 => 10, 25 => 25, 50 => 50, 100 => 100]],
                'general.invoice_due_days' => ['Invoice Due (days)', 'number', ['required', 'integer', 'min:0', 'max:365']],
                'general.invoice_template' => ['Invoice Template', 'select', ['required', 'in:classic,modern,compact'], ['classic' => 'Classic', 'modern' => 'Modern', 'compact' => 'Compact']],
                'general.invoice_terms' => ['Default Invoice Terms', 'textarea', ['nullable', 'string', 'max:3000']],
            ],
            'theme' => [
                'theme.mode' => ['Default Theme Mode', 'select', ['required', 'in:light,dark,auto'], ['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Automatic (system)']],
                'theme.primary_color' => ['Primary Color', 'color', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
                'theme.secondary_color' => ['Secondary Color', 'color', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
                'theme.sidebar_color' => ['Sidebar Color', 'color', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
                'theme.header_color' => ['Header Color', 'color', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
                'theme.font' => ['Font', 'select', ['required', 'in:Inter,Roboto,Open Sans,Poppins,Lato,system-ui'],
                    ['Inter' => 'Inter', 'Roboto' => 'Roboto', 'Open Sans' => 'Open Sans', 'Poppins' => 'Poppins', 'Lato' => 'Lato', 'system-ui' => 'System default']],
                'theme.sidebar_collapsed' => ['Collapse sidebar by default', 'switch', $bool],
                'theme.logo_height' => ['Logo Height (px)', 'number', ['required', 'integer', 'min:16', 'max:120']],
                'theme.dashboard_layout' => ['Dashboard Layout', 'select', ['required', 'in:standard,compact,charts_first'],
                    ['standard' => 'Standard', 'compact' => 'Compact cards', 'charts_first' => 'Charts first']],
            ],
            'email' => [
                'mail.override' => ['Use these SMTP settings (otherwise .env is used)', 'switch', $bool],
                'mail.host' => ['SMTP Host', 'text', ['nullable', 'string', 'max:255']],
                'mail.port' => ['SMTP Port', 'number', ['nullable', 'integer', 'min:1', 'max:65535']],
                'mail.username' => ['SMTP Username', 'text', ['nullable', 'string', 'max:255']],
                'mail.password' => ['SMTP Password', 'secret', ['nullable', 'string', 'max:255']],
                'mail.encryption' => ['Encryption', 'select', ['nullable', 'in:tls,ssl,none'], ['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'None']],
                'mail.from_name' => ['Sender Name', 'text', ['required', 'string', 'max:120']],
                'mail.from_address' => ['Sender Email', 'email', ['required', 'email', 'max:255']],
                'verification.email' => ['Require email verification', 'switch', $bool],
                'notifications.invoice_email' => ['Allow emailing invoices to customers', 'switch', $bool],
                'notifications.admin_approval_email' => ['Email administrators about new registrations', 'switch', $bool],
                'notifications.low_stock_email' => ['Email low stock alerts', 'switch', $bool],
                'notifications.payment_receipt_email' => ['Email payment receipts to customers', 'switch', $bool],
            ],
            'sms' => [
                'verification.sms' => ['Require SMS phone verification', 'switch', $bool],
                'sms.provider' => ['SMS Provider', 'select', ['required', 'in:log,twilio,vonage,http'],
                    ['log' => 'Log only (development - no SMS sent)', 'twilio' => 'Twilio', 'vonage' => 'Vonage (Nexmo)', 'http' => 'Generic HTTP gateway']],
                'sms.api_key' => ['API Key / Account SID / Bearer token', 'secret', ['nullable', 'string', 'max:255']],
                'sms.api_secret' => ['API Secret / Auth Token', 'secret', ['nullable', 'string', 'max:255']],
                'sms.sender' => ['Sender ID / From Number', 'text', ['nullable', 'string', 'max:30']],
                'sms.http_url' => ['HTTP Gateway URL (generic provider)', 'url', ['nullable', 'url', 'max:255']],
                'sms.code_expiry_minutes' => ['Verification Code Expiry (minutes)', 'number', ['required', 'integer', 'min:1', 'max:60']],
                'sms.rate_limit_per_hour' => ['Max SMS per number per hour', 'number', ['required', 'integer', 'min:1', 'max:50']],
            ],
            'verification' => [
                'verification.admin_approval' => ['New accounts require administrator approval', 'switch', $bool],
                'verification.email' => ['Require email verification', 'switch', $bool],
                'verification.sms' => ['Require SMS phone verification', 'switch', $bool],
                'verification.two_factor' => ['Two-Factor Authentication', 'select', ['required', 'in:off,optional,required'],
                    ['off' => 'Disabled', 'optional' => 'Optional (users may enable)', 'required' => 'Required for all users']],
                'verification.login_notifications' => ['Email users on every sign-in', 'switch', $bool],
            ],
            'integrations' => [
                'vin.provider' => ['Active VIN Decoder', 'select', ['required', 'in:nhtsa,alternative'],
                    ['nhtsa' => 'NHTSA vPIC (free, no key)', 'alternative' => 'Alternative provider (configured below)']],
                'vin.nhtsa_url' => ['NHTSA vPIC Base URL', 'url', ['required', 'url', 'max:255']],
                'vin.timeout' => ['VIN API Timeout (seconds)', 'number', ['required', 'integer', 'min:3', 'max:60']],
                'payment.gateway' => ['Payment Gateway', 'select', ['required', 'in:none,stripe,square,other'],
                    ['none' => 'None (record payments manually)', 'stripe' => 'Stripe', 'square' => 'Square', 'other' => 'Other']],
                'payment.public_key' => ['Gateway Publishable Key', 'text', ['nullable', 'string', 'max:255']],
                'payment.secret_key' => ['Gateway Secret Key', 'secret', ['nullable', 'string', 'max:255']],
            ],
            'operations' => [
                'inventory.low_stock_default' => ['Default Minimum Stock Level', 'number', ['required', 'integer', 'min:0']],
                'inventory.allow_negative' => ['Allow negative stock globally (not recommended)', 'switch', $bool],
                'payroll.daily_overtime_threshold' => ['Daily Overtime Threshold (hours)', 'number', ['required', 'numeric', 'min:0', 'max:24']],
                'payroll.weekly_overtime_threshold' => ['Weekly Overtime Threshold (hours, 0 = off)', 'number', ['required', 'numeric', 'min:0', 'max:168']],
                'payroll.overtime_multiplier' => ['Default Overtime Multiplier', 'number', ['required', 'numeric', 'min:1', 'max:5']],
                'payroll.work_start' => ['Default Work Start Time', 'time', ['required', 'date_format:H:i']],
                'payroll.late_grace_minutes' => ['Late Grace Period (minutes)', 'number', ['required', 'integer', 'min:0', 'max:120']],
                'payroll.payment_due_days' => ['Payroll overdue after pay date (days)', 'number', ['required', 'integer', 'min:0', 'max:60']],
                'reminders.enabled' => ['Send customer payment reminders', 'switch', $bool],
                'reminders.days_after_due' => ['First reminder (days after due date)', 'number', ['required', 'integer', 'min:0', 'max:90']],
                'reminders.repeat_every_days' => ['Repeat reminder every (days)', 'number', ['required', 'integer', 'min:1', 'max:90']],
                'backup.enabled' => ['Scheduled database backups', 'switch', $bool],
                'backup.frequency' => ['Backup Frequency', 'select', ['required', 'in:daily,weekly'], ['daily' => 'Daily', 'weekly' => 'Weekly']],
                'backup.keep_days' => ['Keep backups for (days)', 'number', ['required', 'integer', 'min:1', 'max:3650']],
                'retention.audit_logs_days' => ['Keep audit logs (days)', 'number', ['required', 'integer', 'min:30']],
                'retention.login_history_days' => ['Keep login history (days)', 'number', ['required', 'integer', 'min:30']],
                'retention.integration_logs_days' => ['Keep integration logs (days)', 'number', ['required', 'integer', 'min:7']],
            ],
            'security' => [
                'security.session_lifetime' => ['Session Idle Timeout (minutes)', 'number', ['required', 'integer', 'min:5', 'max:1440']],
                'security.max_login_attempts' => ['Max Login Attempts per Minute', 'number', ['required', 'integer', 'min:3', 'max:20']],
                'security.password_min_length' => ['Minimum Password Length', 'number', ['required', 'integer', 'min:8', 'max:64']],
                'maintenance.enabled' => ['Maintenance mode (only administrators can sign in)', 'switch', $bool],
                'maintenance.message' => ['Maintenance Message', 'textarea', ['nullable', 'string', 'max:500']],
            ],
            default => [],
        };
    }

    public function index()
    {
        return view('admin.settings.index', ['sections' => self::sections()]);
    }

    public function section(Request $request, string $section)
    {
        $data = ['section' => $section, 'sections' => self::sections(), 'schema' => self::schema($section), 'settings' => $this->settings];

        return match ($section) {
            'integrations' => view('admin.settings.integrations', $data + ['integrations' => ApiIntegration::orderBy('type')->get()]),
            'statuses' => view('admin.settings.statuses', $data + ['statuses' => VehicleStatus::orderBy('sort_order')->get()]),
            'lookups' => view('admin.settings.lookups', $data + ['lookups' => $this->lookups()]),
            'custom-fields' => view('admin.settings.custom-fields', $data + ['fields' => CustomField::orderBy('sort_order')->get()]),
            'backups' => view('admin.settings.backups', $data + ['backups' => app(BackupService::class)->list()]),
            'integration-logs' => view('admin.settings.integration-logs', $data + ['logs' => IntegrationLog::latest('id')->paginate(50)]),
            default => view('admin.settings.section', $data),
        };
    }

    public function save(Request $request, string $section)
    {
        $schema = self::schema($section);
        abort_if(! $schema, 404);
        $rules = [];
        foreach ($schema as $key => $def) {
            $rules[str_replace('.', '__', $key)] = $def[2];
        }
        $input = $request->validate($rules);

        $changed = [];
        foreach ($schema as $key => $def) {
            $field = str_replace('.', '__', $key);
            $type = $def[1];

            if ($type === 'image') {
                if ($request->hasFile($field)) {
                    $old = $this->settings->get($key);
                    $this->settings->set($key, Uploads::image($request->file($field), 'branding'));
                    if ($old) {
                        Storage::disk('public')->delete($old);
                    }
                    $changed[] = $key;
                } elseif ($request->boolean($field.'_remove')) {
                    $this->settings->set($key, null);
                    $changed[] = $key;
                }

                continue;
            }
            if ($type === 'secret') {
                // Blank = keep existing secret; "__clear__" removes it.
                $value = $input[$field] ?? null;
                if ($value === '__clear__') {
                    $this->settings->set($key, null);
                    $changed[] = $key;
                } elseif ($value !== null && $value !== '') {
                    $this->settings->set($key, $value);
                    $changed[] = $key;
                }

                continue;
            }
            $value = $type === 'switch' ? $request->boolean($field) : ($input[$field] ?? null);
            if ((string) $this->settings->get($key) !== (string) (is_bool($value) ? (int) $value : $value)) {
                $changed[] = $key;
            }
            $this->settings->set($key, $value);
        }

        AuditLogger::log('settings_updated', null, "Settings ({$section}) updated", [], ['keys' => $changed]);

        return back()->with('success', 'Settings saved.');
    }

    public function testEmail(Request $request)
    {
        $request->validate(['to' => ['required', 'email']]);
        try {
            Mail::raw('This is a test email from '.settings('general.company_name').'. Your email settings are working.', function ($m) use ($request) {
                $m->to($request->to)->subject('Test email');
            });
            IntegrationLog::record('mail', 'test', true, 'Sent to '.$request->to);

            return back()->with('success', 'Test email sent to '.$request->to.' using mailer "'.config('mail.default').'".'
                .(config('mail.default') === 'log' ? ' (Log mailer: check storage/logs/laravel.log — no real email was delivered.)' : ''));
        } catch (Throwable $e) {
            IntegrationLog::record('mail', 'test', false, $e->getMessage());

            return back()->withErrors(['to' => 'Sending failed: '.$e->getMessage()]);
        }
    }

    public function testSms(Request $request, SmsManager $sms)
    {
        $request->validate(['to' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/']]);
        $result = $sms->send($request->to, 'Test message from '.settings('general.company_name'));

        return $result['success']
            ? back()->with('success', 'SMS provider "'.$sms->provider()->name().'": '.$result['message'])
            : back()->withErrors(['to' => 'SMS failed: '.$result['message']]);
    }

    /* ---------- Integrations ---------- */

    public function storeIntegration(Request $request)
    {
        ApiIntegration::create($this->integrationData($request));

        return back()->with('success', 'Integration added.');
    }

    public function updateIntegration(Request $request, ApiIntegration $integration)
    {
        $integration->update($this->integrationData($request, $integration));

        return back()->with('success', 'Integration updated.');
    }

    public function destroyIntegration(ApiIntegration $integration)
    {
        $integration->delete();

        return back()->with('success', 'Integration removed.');
    }

    public function testIntegration(ApiIntegration $integration, VinDecoderService $vin)
    {
        $ok = false;
        $msg = 'No automated test is available for this integration type; verify it with the provider dashboard.';
        if ($integration->type === 'vin_decoder') {
            $prev = settings('vin.provider');
            $this->settings->set('vin.provider', 'alternative');
            try {
                $r = $vin->decode('1HGCM82633A004352');
                [$ok, $msg] = [$r['success'], $r['message']];
            } finally {
                $this->settings->set('vin.provider', $prev);
            }
        } elseif ($integration->type === 'other' && ($url = $integration->config['url'] ?? null)) {
            try {
                $res = Http::timeout(10)->get($url);
                [$ok, $msg] = [$res->successful(), 'HTTP '.$res->status()];
            } catch (Throwable $e) {
                $msg = $e->getMessage();
            }
        }
        $integration->forceFill(['last_tested_at' => now(), 'last_test_status' => $ok ? 'ok' : 'failed'])->save();
        IntegrationLog::record($integration->provider, 'test', $ok, $msg);

        return $ok ? back()->with('success', 'Test passed: '.$msg) : back()->withErrors(['integration' => 'Test result: '.$msg]);
    }

    protected function integrationData(Request $request, ?ApiIntegration $existing = null): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:vin_decoder,payment_gateway,email,sms,other'],
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', 'string', 'max:50', 'alpha_dash'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:500'],
            'auth_header' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-]+$/'],
            'field_map' => ['nullable', 'json'],
        ]);
        $creds = $existing?->credentials ?? [];
        foreach (['api_key', 'api_secret'] as $k) {
            if (filled($data[$k] ?? null)) {
                $creds[$k] = $data[$k] === '__clear__' ? null : $data[$k];
            }
        }

        return [
            'type' => $data['type'], 'name' => $data['name'], 'provider' => Str::lower($data['provider']),
            'is_active' => $request->boolean('is_active'), 'is_default' => $request->boolean('is_default'),
            'credentials' => array_filter($creds),
            'config' => array_filter(['url' => $data['url'] ?? null, 'auth_header' => $data['auth_header'] ?? null,
                'field_map' => filled($data['field_map'] ?? null) ? json_decode($data['field_map'], true) : null]),
        ];
    }

    /* ---------- Vehicle statuses ---------- */

    public function storeStatus(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'color' => ['required', 'in:primary,secondary,success,danger,warning,info,dark,teal,purple'],
            'sort_order' => ['nullable', 'integer']]);
        $slug = Str::slug($data['name'], '_');
        if (VehicleStatus::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A status with this name already exists.']);
        }
        VehicleStatus::create($data + ['slug' => $slug, 'sort_order' => $data['sort_order'] ?? (VehicleStatus::max('sort_order') + 1)]);

        return back()->with('success', 'Status added.');
    }

    public function updateStatus(Request $request, VehicleStatus $status)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'color' => ['required', 'in:primary,secondary,success,danger,warning,info,dark,teal,purple'],
            'sort_order' => ['required', 'integer'], 'is_active' => ['nullable', 'boolean']]);
        $status->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Status updated.');
    }

    public function destroyStatus(VehicleStatus $status)
    {
        if ($status->is_system) {
            return back()->withErrors(['status' => 'System statuses are used by automatic workflows. Deactivate it instead.']);
        }
        if (Vehicle::where('repair_status', $status->slug)->exists()) {
            return back()->withErrors(['status' => 'Vehicles still use this status.']);
        }
        $status->delete();

        return back()->with('success', 'Status deleted.');
    }

    /* ---------- Lookups ---------- */

    protected function lookups(): array
    {
        return [
            'departments' => ['title' => 'Departments', 'items' => Department::withCount('employees')->orderBy('name')->get()],
            'part_categories' => ['title' => 'Part Categories', 'items' => PartCategory::withCount('parts')->orderBy('name')->get()],
            'stock_locations' => ['title' => 'Warehouses / Stock Locations', 'items' => StockLocation::orderBy('name')->get()],
            'service_types' => ['title' => 'Service Types', 'items' => ServiceType::orderBy('category')->orderBy('name')->get()],
        ];
    }

    public function storeLookup(Request $request, string $type)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => [$type === 'service_types' ? 'required' : 'nullable', 'in:repair,interior,cleaning'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'default_cost' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        match ($type) {
            'departments' => Department::firstOrCreate(['name' => $data['name']]),
            'part_categories' => PartCategory::firstOrCreate(['name' => $data['name']]),
            'stock_locations' => StockLocation::firstOrCreate(['name' => $data['name']], ['address' => $data['address'] ?? null]),
            'service_types' => ServiceType::updateOrCreate(['category' => $data['category'], 'name' => $data['name']],
                ['default_price' => $data['default_price'] ?? 0, 'default_cost' => $data['default_cost'] ?? 0]),
            default => abort(404),
        };

        return back()->with('success', 'Saved.');
    }

    public function destroyLookup(string $type, int $id)
    {
        $model = match ($type) {
            'departments' => Department::findOrFail($id),
            'part_categories' => PartCategory::findOrFail($id),
            'stock_locations' => StockLocation::findOrFail($id),
            'service_types' => ServiceType::findOrFail($id),
            default => abort(404),
        };
        if ($type === 'stock_locations' && PartStock::where('location_id', $id)->where('quantity', '!=', 0)->exists()) {
            return back()->withErrors(['lookup' => 'This location still holds stock. Transfer it first.']);
        }
        $model->delete();

        return back()->with('success', 'Deleted.');
    }

    /* ---------- Custom fields ---------- */

    public function storeCustomField(Request $request)
    {
        $data = $this->customFieldData($request);
        if (CustomField::where('module', 'vehicles')->where('key', $data['key'])->exists()) {
            return back()->withErrors(['label' => 'A field with this key already exists.']);
        }
        CustomField::create($data + ['module' => 'vehicles']);

        return back()->with('success', 'Custom field added.');
    }

    public function updateCustomField(Request $request, CustomField $field)
    {
        $data = $this->customFieldData($request);
        unset($data['key']);
        $field->update($data);

        return back()->with('success', 'Custom field updated.');
    }

    public function destroyCustomField(CustomField $field)
    {
        $field->delete();

        return back()->with('success', 'Custom field deleted (existing values are kept on vehicles).');
    }

    protected function customFieldData(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'options' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        return [
            'label' => $data['label'], 'key' => Str::slug($data['label'], '_'), 'type' => $data['type'],
            'options' => $data['type'] === 'select' ? array_values(array_filter(array_map('trim', explode(',', $data['options'] ?? '')))) : null,
            'is_required' => $request->boolean('is_required'), 'is_active' => $request->boolean('is_active', true),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /* ---------- Backups ---------- */

    public function runBackup(BackupService $backups)
    {
        try {
            $file = $backups->run();

            return back()->with('success', 'Backup created: '.basename($file));
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
    }

    public function downloadBackup(string $file)
    {
        abort_unless(preg_match('/^backup-[0-9_\-]+\.sql\.gz$/', $file), 404);
        $path = BackupService::DIR.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);
        AuditLogger::log('backup_downloaded', null, "Backup {$file} downloaded");

        return Storage::disk('local')->download($path);
    }
}
