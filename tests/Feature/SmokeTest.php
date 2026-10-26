<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\ServiceJob;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Services\PayrollService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page renders for a Super Administrator and every list endpoint returns JSON. */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_render_with_demo_data(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->seed(DemoDataSeeder::class);
        $this->actingAs($admin);
        $period = PayrollPeriod::create(['name' => 'Smoke', 'frequency' => 'monthly', 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'status' => 'draft']);
        app(PayrollService::class)->generate($period);

        $v = Vehicle::first();
        $p = Part::first();
        $c = Customer::first();
        $s = Supplier::first();
        $e = Employee::first();
        $j = ServiceJob::first();
        $i = Invoice::first();
        $pay = Payment::first();

        $pages = [
            '/dashboard', '/profile', '/profile/security', '/notifications', '/search?q=demo',
            '/vehicles', '/vehicles/create', "/vehicles/{$v->id}", "/vehicles/{$v->id}/edit", "/vehicles/{$v->id}/label", "/vehicles/{$v->id}/sheet",
            "/vehicles/{$v->id}/inspections/create", '/vehicles/import',
            '/parts', '/parts/create', "/parts/{$p->id}", "/parts/{$p->id}/edit", "/parts/{$p->id}/label", '/parts/movements', '/parts/import',
            '/purchase-orders', '/purchase-orders/create', '/purchase-orders/create?low_stock=1',
            '/suppliers', '/suppliers/create', "/suppliers/{$s->id}", "/suppliers/{$s->id}/edit",
            '/customers', '/customers/create', "/customers/{$c->id}", "/customers/{$c->id}/edit", "/customers/{$c->id}/statement",
            '/employees', '/employees/create', "/employees/{$e->id}", "/employees/{$e->id}/edit",
            '/attendance', '/payroll', "/payroll/{$period->id}", '/payroll/payments', '/tasks',
            '/service-jobs', '/service-jobs?category=repair', '/service-jobs/create?category=interior', "/service-jobs/{$j->id}", "/service-jobs/{$j->id}/edit", "/service-jobs/{$j->id}/print",
            '/invoices', '/invoices/create', "/invoices/create?service_job_id={$j->id}", "/invoices/create?type=vehicle_sale&vehicle_id={$v->id}",
            "/invoices/{$i->id}", "/invoices/{$i->id}/print",
            '/payments', '/payments/create', "/payments/{$pay->id}", "/payments/{$pay->id}/receipt",
            '/reports', '/admin/users', '/admin/users/create', "/admin/users/{$admin->id}", '/admin/roles', '/admin/roles/create', '/admin/audit-logs',
            '/admin/settings',
        ];
        foreach (array_keys(SettingsController::sections()) as $section) {
            $pages[] = "/admin/settings/{$section}";
        }

        foreach ($pages as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->status(), "GET {$url} returned {$res->status()}");
        }

        $json = ['/vehicles', '/parts', '/parts/movements', '/purchase-orders', '/suppliers', '/customers', '/employees', '/attendance', '/payroll',
            '/payroll/payments', '/tasks', '/service-jobs', '/invoices', '/payments', '/admin/users', '/admin/audit-logs',
            '/lookup/customers?q=', '/lookup/vehicles?q=', '/lookup/parts?q=', '/lookup/employees?q=', '/lookup/service_jobs?q=', '/lookup/suppliers?q=', '/lookup/users?q=',
            "/payments/open-invoices/{$i->customer_id}", '/notifications/feed', '/dashboard/data'];
        foreach ($json as $url) {
            $res = $this->getJson($url);
            $this->assertSame(200, $res->status(), "GET(JSON) {$url} returned {$res->status()}: ".substr($res->getContent(), 0, 300));
        }

        foreach (['/purchase-orders/export', '/admin/users/export', '/admin/audit-logs/export', '/tasks/export', '/vehicles/export', '/parts/export', '/customers/export', '/suppliers/export', '/employees/export', '/invoices/export', '/payments/export', '/attendance/export', '/service-jobs/export'] as $url) {
            foreach (['csv', 'xlsx', 'print'] as $fmt) {
                $this->assertSame(200, $this->get("{$url}?format={$fmt}")->baseResponse->getStatusCode(), "Export {$url} {$fmt}");
            }
        }
    }

    public function test_bulk_actions_respect_permissions(): void
    {
        $this->seed(DemoDataSeeder::class);
        $ids = Customer::pluck('id')->all();
        $this->actingAs($this->userWithRole('Viewer'))->postJson('/customers/bulk', ['ids' => $ids, 'action' => 'deactivate'])->assertForbidden();
        $this->actingAs($this->admin())->postJson('/customers/bulk', ['ids' => $ids, 'action' => 'deactivate'])->assertOk();
        $this->assertSame(0, Customer::where('is_active', true)->count());
        // customers with invoices are never bulk-deleted
        $this->actingAs($this->admin())->postJson('/customers/bulk', ['ids' => $ids, 'action' => 'delete'])->assertOk();
        $this->assertTrue(Customer::whereHas('invoices')->exists());

        $tech = $this->userWithRole('Repair Technician');
        $jobIds = ServiceJob::pluck('id')->all();
        $res = $this->actingAs($tech)->postJson('/service-jobs/bulk', ['ids' => $jobIds, 'action' => 'status', 'value' => 'on_hold'])->assertOk();
        $this->assertStringContainsString('0 job(s) updated', $res->json('message')); // not assigned to any
    }

    public function test_limited_role_pages_render(): void
    {
        $this->seed(DemoDataSeeder::class);
        foreach (['Sales Representative', 'Repair Technician', 'Accountant', 'Employee', 'Viewer', 'Parts Manager', 'HR / Payroll Manager', 'Inventory Manager', 'Manager'] as $role) {
            $user = $this->userWithRole($role);
            Employee::factory()->create(['user_id' => $user->id]);
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->getJson('/dashboard/data')->assertOk();
            $this->actingAs($user)->get('/profile')->assertOk();
        }
    }
}
