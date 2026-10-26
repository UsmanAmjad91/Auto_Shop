<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_hours_breaks_and_overtime(): void
    {
        $this->actingAs($this->admin());
        $e = Employee::factory()->create();
        $r = app(AttendanceService::class)->saveManual(['employee_id' => $e->id, 'work_date' => '2026-03-02', 'clock_in' => '08:00', 'clock_out' => '18:30',
            'break_minutes' => 30, 'status' => 'present']);
        $this->assertEquals(10, $r->total_hours);
        $this->assertEquals(8, $r->regular_hours);
        $this->assertEquals(2, $r->overtime_hours);
    }

    public function test_self_service_punch_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 07:55'));
        $u = $this->userWithRole('Employee');
        $e = Employee::factory()->create(['user_id' => $u->id]);
        $this->actingAs($u)->postJson(route('attendance.punch'), ['action' => 'clock_in'])->assertOk();
        $this->actingAs($u)->postJson(route('attendance.punch'), ['action' => 'clock_in'])->assertStatus(422);
        Carbon::setTestNow(Carbon::parse('2026-03-02 12:00'));
        $this->actingAs($u)->postJson(route('attendance.punch'), ['action' => 'break_start'])->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-03-02 12:30'));
        $this->actingAs($u)->postJson(route('attendance.punch'), ['action' => 'break_end'])->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-03-02 16:55'));
        $this->actingAs($u)->postJson(route('attendance.punch'), ['action' => 'clock_out'])->assertOk();
        $r = AttendanceRecord::where('employee_id', $e->id)->first();
        $this->assertEquals(8.5, $r->total_hours);
        $this->assertSame('present', $r->status);
        $this->assertSame(30, $r->break_minutes);
        Carbon::setTestNow();
    }

    public function test_manager_correction_is_audited(): void
    {
        $manager = $this->userWithRole('HR / Payroll Manager');
        $e = Employee::factory()->create();
        $rec = AttendanceRecord::create(['employee_id' => $e->id, 'work_date' => today()->subDay(), 'status' => 'absent']);
        $this->actingAs($manager)->putJson(route('attendance.update', $rec), ['employee_id' => $e->id, 'work_date' => today()->subDay()->toDateString(),
            'clock_in' => '09:00', 'clock_out' => '17:00', 'status' => 'present', 'notes' => 'Forgot to clock in'])->assertOk();
        $rec->refresh();
        $this->assertSame($manager->id, $rec->corrected_by);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $rec->id, 'event' => 'updated']);
    }

    public function test_hourly_payroll_uses_historical_rate_and_overtime(): void
    {
        $this->actingAs($this->admin());
        $e = Employee::factory()->create();
        $e->payRates()->create(['pay_type' => 'hourly', 'rate' => 20, 'overtime_multiplier' => 1.5, 'effective_from' => '2026-01-01', 'effective_to' => '2026-03-31']);
        $e->payRates()->create(['pay_type' => 'hourly', 'rate' => 25, 'overtime_multiplier' => 1.5, 'effective_from' => '2026-04-01']);
        $att = app(AttendanceService::class);
        foreach (['2026-03-02', '2026-03-03'] as $d) {
            $att->saveManual(['employee_id' => $e->id, 'work_date' => $d, 'clock_in' => '08:00', 'clock_out' => '18:00', 'status' => 'present']); // 10h
        }
        $period = PayrollPeriod::create(['name' => 'March', 'frequency' => 'monthly', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'pay_date' => '2026-03-31', 'status' => 'draft']);
        $svc = app(PayrollService::class);
        $this->assertSame(1, $svc->generate($period));

        $entry = $period->entries()->first();
        $this->assertEquals(20, $entry->rate);               // March rate, not current
        $this->assertEquals(16, $entry->regular_hours);
        $this->assertEquals(4, $entry->overtime_hours);
        $this->assertEquals(320, $entry->base_pay);           // 16 * 20
        $this->assertEquals(120, $entry->overtime_pay);       // 4 * 20 * 1.5

        $svc->updateAdjustments($entry, ['base_pay' => 320, 'overtime_pay' => 120, 'bonuses' => 50, 'deductions' => 40, 'advances' => 30]);
        $entry->refresh();
        $this->assertEquals(490, $entry->gross_earnings);
        $this->assertEquals(420, $entry->net_pay);

        // cannot pay before approval
        try {
            $svc->pay($entry, ['amount' => 100, 'payment_date' => '2026-03-31', 'method' => 'cash']);
            $this->fail();
        } catch (ValidationException) {
        }
        $svc->approve($period);
        $svc->pay($entry->fresh(), ['amount' => 200, 'payment_date' => '2026-03-31', 'method' => 'cash']);
        $this->assertEquals(220, $entry->fresh()->remaining_payable);
        $svc->pay($entry->fresh(), ['amount' => 220, 'payment_date' => '2026-03-31', 'method' => 'bank_transfer']);
        $this->assertSame('paid', $entry->fresh()->payment_status);
        $this->assertSame('closed', $period->fresh()->status);

        // approved payroll is locked
        $this->expectException(ValidationException::class);
        $svc->updateAdjustments($entry->fresh(), ['base_pay' => 1, 'overtime_pay' => 0, 'bonuses' => 0, 'deductions' => 0, 'advances' => 0]);
    }

    public function test_monthly_salary_prorates_and_payslip_downloads(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $e = Employee::factory()->create();
        $e->payRates()->create(['pay_type' => 'monthly', 'rate' => 3100, 'overtime_multiplier' => 1.5, 'effective_from' => '2025-01-01']);
        $period = PayrollPeriod::create(['name' => 'Half March', 'frequency' => 'custom', 'start_date' => '2026-03-01', 'end_date' => '2026-03-15', 'status' => 'draft']);
        app(PayrollService::class)->generate($period);
        $entry = $period->entries()->first();
        $this->assertEquals(1500, $entry->base_pay); // 3100 * 15/31
        $this->actingAs($admin)->get(route('payroll.entries.payslip', $entry))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_can_only_view_own_approved_payslip(): void
    {
        $u = $this->userWithRole('Employee');
        $me = Employee::factory()->create(['user_id' => $u->id]);
        $other = Employee::factory()->create();
        foreach ([$me, $other] as $e) {
            $e->payRates()->create(['pay_type' => 'fixed', 'rate' => 1000, 'overtime_multiplier' => 1.5, 'effective_from' => '2025-01-01']);
        }
        $period = PayrollPeriod::create(['name' => 'P', 'frequency' => 'monthly', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'status' => 'draft']);
        $this->actingAs($this->admin());
        app(PayrollService::class)->generate($period);
        $mine = $period->entries()->where('employee_id', $me->id)->first();
        $theirs = $period->entries()->where('employee_id', $other->id)->first();

        $this->actingAs($u)->get(route('payroll.entries.payslip', $mine))->assertForbidden(); // still draft
        $period->update(['status' => 'approved']);
        $this->actingAs($u)->get(route('payroll.entries.payslip', $mine))->assertOk();
        $this->actingAs($u)->get(route('payroll.entries.payslip', $theirs))->assertForbidden();
        $this->actingAs($u)->get(route('payroll.index'))->assertForbidden();
    }
}
