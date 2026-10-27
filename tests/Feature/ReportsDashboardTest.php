<?php

namespace Tests\Feature;

use App\Services\ReportService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widgets_respect_permissions(): void
    {
        $this->vehicle();
        $admin = $this->actingAs($this->admin())->getJson(route('dashboard.data'))->assertOk();
        $keys = array_column($admin->json('cards'), 'key');
        $this->assertContains('total_sales', $keys);
        $this->assertContains('payables', $keys);
        $this->assertArrayHasKey('sales', $admin->json('charts'));

        $viewer = $this->actingAs($this->userWithRole('Viewer'))->getJson(route('dashboard.data'))->assertOk();
        $vkeys = array_column($viewer->json('cards'), 'key');
        $this->assertContains('total_vehicles', $vkeys);
        $this->assertNotContains('total_sales', $vkeys);
        $this->assertNotContains('total_employees', $vkeys);
        $this->assertArrayNotHasKey('sales', $viewer->json('charts'));
    }

    public function test_every_report_renders_and_exports(): void
    {
        $this->actingAs($admin = $this->admin());
        $this->seed(DemoDataSeeder::class);
        foreach (array_keys(ReportService::definitions()) as $key) {
            $this->actingAs($admin)->get(route('reports.show', $key))->assertOk();
            $this->actingAs($admin)->get(route('reports.export', [$key, 'format' => 'csv']))->assertOk();
        }
        $this->actingAs($admin)->get(route('reports.export', ['monthly_performance', 'format' => 'xlsx']))->assertOk();
        $this->actingAs($admin)->get(route('reports.export', ['inventory_value', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_financial_reports_hidden_without_permission(): void
    {
        $user = $this->userWithRole('Inventory Manager'); // reports.view but no financials.view
        $this->actingAs($user)->get(route('reports.show', 'monthly_performance'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.show', 'low_stock'))->assertOk();
    }
}
