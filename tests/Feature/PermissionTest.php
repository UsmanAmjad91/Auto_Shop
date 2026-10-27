<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\ServiceJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_or_delete_vehicles(): void
    {
        $viewer = $this->userWithRole('Viewer');
        $vehicle = $this->vehicle();

        $this->actingAs($viewer)->get(route('vehicles.index'))->assertOk();
        $this->actingAs($viewer)->get(route('vehicles.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('vehicles.store'), [])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('vehicles.destroy', $vehicle))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('vehicles.bulk'), ['ids' => [$vehicle->id], 'action' => 'delete'])->assertForbidden();
    }

    public function test_cost_columns_hidden_without_view_costs(): void
    {
        $this->vehicle(['purchase_price' => 12345]);
        $viewer = $this->userWithRole('Viewer');
        $row = $this->actingAs($viewer)->getJson(route('vehicles.index'))->assertOk()->json('data.0');
        $this->assertNull($row['purchase_price']);
        $this->assertNull($row['total_investment']);

        $accountant = $this->userWithRole('Accountant');
        $row = $this->actingAs($accountant)->getJson(route('vehicles.index'))->json('data.0');
        $this->assertEquals(12345, $row['purchase_price']);
    }

    public function test_settings_require_settings_manage(): void
    {
        $manager = $this->userWithRole('Manager');
        $this->actingAs($manager)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('settings.save', 'general'), [])->assertForbidden();
        $this->actingAs($this->userWithRole('Administrator'))->get(route('settings.section', 'general'))->assertOk();
    }

    public function test_technician_only_sees_assigned_service_jobs(): void
    {
        $techUser = $this->userWithRole('Repair Technician');
        $tech = Employee::factory()->create(['user_id' => $techUser->id]);
        $jobs = app(ServiceJobService::class);
        $mine = $jobs->create(['category' => 'repair', 'vehicle_id' => $this->vehicle()->id, 'title' => 'Mine', 'priority' => 'normal'], [$tech->id => []]);
        $other = $jobs->create(['category' => 'repair', 'vehicle_id' => $this->vehicle()->id, 'title' => 'Other', 'priority' => 'normal']);

        $data = $this->actingAs($techUser)->getJson(route('service-jobs.index'))->assertOk()->json('data');
        $this->assertSame([$mine->id], array_column($data, 'id'));
        $this->actingAs($techUser)->get(route('service-jobs.show', $other))->assertForbidden();
        $this->actingAs($techUser)->get(route('service-jobs.show', $mine))->assertOk();
        // Technicians cannot approve quality checks
        $this->actingAs($techUser)->postJson(route('service-jobs.status', $mine), ['status' => 'quality_checked'])->assertForbidden();
    }

    public function test_employee_sees_only_own_attendance(): void
    {
        $u = $this->userWithRole('Employee');
        $me = Employee::factory()->create(['user_id' => $u->id]);
        $other = Employee::factory()->create();
        AttendanceRecord::create(['employee_id' => $me->id, 'work_date' => today(), 'status' => 'present']);
        AttendanceRecord::create(['employee_id' => $other->id, 'work_date' => today(), 'status' => 'present']);

        $data = $this->actingAs($u)->getJson(route('attendance.index'))->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($me->id, $data[0]['employee_id']);
    }

    public function test_only_super_admin_can_grant_super_admin_role(): void
    {
        $admin = $this->userWithRole('Administrator');
        $target = $this->userWithRole('Viewer');
        $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'status' => 'approved', 'roles' => ['Super Administrator'],
        ])->assertSessionHasErrors('roles.0');
        $this->assertFalse($target->fresh()->hasRole('Super Administrator'));
    }

    public function test_custom_role_permissions_are_enforced(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('roles.store'), ['name' => 'Lot Attendant', 'permissions' => ['dashboard.view', 'vehicles.view']])->assertRedirect();
        $user = $this->userWithRole('Lot Attendant');
        $this->actingAs($user)->get(route('vehicles.index'))->assertOk();
        $this->actingAs($user)->get(route('parts.index'))->assertForbidden();
    }

    public function test_api_enforces_permissions_with_tokens(): void
    {
        $viewer = $this->userWithRole('Viewer');
        $token = $viewer->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/vehicles')->assertOk();
        $this->withToken($token)->postJson('/api/v1/vehicles', [])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/invoices')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/vehicles')->assertUnauthorized();
    }
}
