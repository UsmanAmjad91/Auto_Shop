<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ServiceJob;
use App\Services\ServiceJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_parts_on_job_deduct_stock_and_roll_into_vehicle_costs(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle(['purchase_price' => 10000]);
        $part = $this->partWithStock(5, ['purchase_cost' => 40, 'selling_price' => 90]);

        $this->actingAs($admin)->post(route('service-jobs.store'), ['category' => 'repair', 'vehicle_id' => $vehicle->id, 'title' => 'Brakes', 'priority' => 'normal', 'is_billable' => 0])->assertRedirect();
        $job = ServiceJob::first();

        $this->actingAs($admin)->postJson(route('service-jobs.items.store', $job), ['item_type' => 'part', 'part_id' => $part->id, 'quantity' => 2])->assertOk();
        $this->actingAs($admin)->postJson(route('service-jobs.items.store', $job), ['item_type' => 'labor', 'description' => 'Labor', 'quantity' => 1.5, 'unit_cost' => 30, 'unit_price' => 100])->assertOk();

        $this->assertSame(3, $part->fresh()->quantity);
        $job->refresh();
        $this->assertEquals(80, $job->parts_cost);
        $this->assertEquals(45, $job->labor_cost);
        $this->assertEquals(330, $job->customer_charge);
        $vehicle->refresh();
        $this->assertEquals(80, $vehicle->parts_cost);
        $this->assertEquals(45, $vehicle->labor_cost);
        $this->assertEquals(10125, $vehicle->total_investment);

        // Insufficient stock is refused atomically
        $this->actingAs($admin)->postJson(route('service-jobs.items.store', $job), ['item_type' => 'part', 'part_id' => $part->id, 'quantity' => 10])->assertStatus(422);
        $this->assertSame(3, $part->fresh()->quantity);
        $this->assertSame(2, $job->items()->count());

        // Removing returns stock and costs
        $item = $job->items()->where('item_type', 'part')->first();
        $this->actingAs($admin)->deleteJson(route('service-jobs.items.destroy', [$job, $item]))->assertOk();
        $this->assertSame(5, $part->fresh()->quantity);
        $this->assertEquals(10045, $vehicle->fresh()->total_investment);
    }

    public function test_status_changes_update_vehicle_repair_and_inventory_status(): void
    {
        $vehicle = $this->vehicle();
        $jobs = app(ServiceJobService::class);
        $job = $jobs->create(['category' => 'repair', 'vehicle_id' => $vehicle->id, 'title' => 'Engine', 'priority' => 'normal']);
        $this->actingAs($this->admin());

        $jobs->changeStatus($job, 'in_progress');
        $this->assertSame('under_repair', $vehicle->fresh()->repair_status);
        $this->assertSame('in_repair', $vehicle->fresh()->inventory_status);
        $this->assertNotNull($job->fresh()->start_date);

        $jobs->changeStatus($job->fresh(), 'completed');
        $this->assertSame('repair_completed', $vehicle->fresh()->repair_status);
        $this->assertSame('available', $vehicle->fresh()->inventory_status);

        $jobs->changeStatus($job->fresh(), 'quality_checked');
        $this->assertSame('ready_for_dispatch', $vehicle->fresh()->repair_status);
        $this->assertGreaterThanOrEqual(3, $vehicle->statusHistory()->where('field', 'repair_status')->count());
    }

    public function test_interior_and_detailing_costs_are_separate_and_not_double_counted(): void
    {
        $vehicle = $this->vehicle(['purchase_price' => 0]);
        $jobs = app(ServiceJobService::class);
        $this->actingAs($this->admin());
        $interior = $jobs->create(['category' => 'interior', 'vehicle_id' => $vehicle->id, 'title' => 'Seats', 'priority' => 'normal']);
        $jobs->addItem($interior, ['item_type' => 'service', 'description' => 'Seat Repair', 'quantity' => 1, 'unit_cost' => 120, 'unit_price' => 200]);
        $detail = $jobs->create(['category' => 'cleaning', 'vehicle_id' => $vehicle->id, 'title' => 'Detail', 'priority' => 'normal']);
        $jobs->addItem($detail, ['item_type' => 'service', 'description' => 'Full Detailing', 'quantity' => 1, 'unit_cost' => 60, 'unit_price' => 150]);

        $v = $vehicle->fresh();
        $this->assertEquals(120, $v->interior_cost);
        $this->assertEquals(60, $v->detailing_cost);
        $this->assertEquals(0, $v->labor_cost);
        $this->assertEquals(180, $v->total_investment);

        // Cancelling excludes the job's costs
        $jobs->changeStatus($detail, 'cancelled');
        $this->assertEquals(120, $vehicle->fresh()->total_investment);
    }

    public function test_cancelling_job_returns_parts(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->partWithStock(4);
        $jobs = app(ServiceJobService::class);
        $this->actingAs($this->admin());
        $job = $jobs->create(['category' => 'repair', 'vehicle_id' => $vehicle->id, 'title' => 'X', 'priority' => 'normal']);
        $jobs->addItem($job, ['item_type' => 'part', 'part_id' => $part->id, 'quantity' => 3]);
        $this->assertSame(1, $part->fresh()->quantity);
        $jobs->changeStatus($job, 'cancelled');
        $this->assertSame(4, $part->fresh()->quantity);
    }

    public function test_multiple_employees_and_checklist(): void
    {
        $admin = $this->admin();
        $e1 = Employee::factory()->create();
        $e2 = Employee::factory()->create();
        $this->actingAs($admin)->post(route('service-jobs.store'), ['category' => 'cleaning', 'vehicle_id' => $this->vehicle()->id, 'title' => 'Wash',
            'priority' => 'normal', 'employees' => [$e1->id => ['role' => 'washer', 'hours_worked' => 1], $e2->id => ['role' => 'detailer', 'hours_worked' => 2]]]);
        $job = ServiceJob::first();
        $this->assertCount(2, $job->employees);
        $this->assertNotEmpty($job->checklist);
        $this->actingAs($admin)->putJson(route('service-jobs.checklist', $job), ['checklist' => [['item' => 'Exterior washed', 'done' => 1]]])->assertOk();
        $this->assertTrue($job->fresh()->checklist[0]['done']);
    }
}
