<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeTask;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PurchaseOrder;
use App\Models\ServiceJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleExpense;
use App\Models\VehicleStatus;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Dashboard data. Each card / chart declares the permission it needs and is
 * only computed for users holding it, so hidden widgets never leak data.
 */
class DashboardService
{
    public function cards(User $user): array
    {
        $cards = [];
        $add = function (string $key, string $perm, string $label, callable $value, string $icon, string $color, ?string $url = null, bool $money = false) use (&$cards, $user) {
            if ($user->can($perm)) {
                $v = $value();
                $cards[] = compact('key', 'label', 'icon', 'color', 'url') + ['value' => $money ? money($v) : number_format($v), 'raw' => $v];
            }
        };

        $inStock = fn () => Vehicle::whereNotIn('inventory_status', ['archived']);
        $add('total_vehicles', 'vehicles.view', 'Total Vehicles', fn () => $inStock()->count(), 'car-front', 'primary', route('vehicles.index'));
        $add('vehicles_available', 'vehicles.view', 'Vehicles Available', fn () => Vehicle::where('inventory_status', 'available')->count(), 'check2-circle', 'success', route('vehicles.index', ['inventory_status' => 'available']));
        $add('vehicles_repair', 'vehicles.view', 'Under Repair', fn () => Vehicle::whereNotIn('inventory_status', ['sold', 'delivered', 'archived'])
            ->whereIn('repair_status', ['under_inspection', 'under_repair', 'waiting_for_parts', 'quality_inspection'])->count(), 'tools', 'info', route('vehicles.index', ['repair_status' => 'under_repair']));
        $add('vehicles_ready', 'vehicles.view', 'Ready for Dispatch', fn () => Vehicle::where('repair_status', 'ready_for_dispatch')->count(), 'truck', 'teal', route('vehicles.index', ['repair_status' => 'ready_for_dispatch']));
        $add('vehicles_sold', 'vehicles.view', 'Vehicles Sold', fn () => Vehicle::whereIn('inventory_status', ['sold', 'delivered'])->count(), 'bag-check', 'purple', route('vehicles.index', ['inventory_status' => 'sold']));
        $add('total_parts', 'parts.view', 'Total Parts (SKUs)', fn () => Part::count(), 'box-seam', 'primary', route('parts.index'));
        $add('low_stock', 'parts.view', 'Low Stock Parts', fn () => Part::where('stock_status', 'low_stock')->count(), 'exclamation-triangle', 'warning', route('parts.index', ['stock_status' => 'low_stock']));
        $add('out_of_stock', 'parts.view', 'Out of Stock', fn () => Part::where('stock_status', 'out_of_stock')->count(), 'x-octagon', 'danger', route('parts.index', ['stock_status' => 'out_of_stock']));
        $add('total_employees', 'employees.view', 'Total Employees', fn () => Employee::where('employment_status', '!=', 'terminated')->count(), 'people', 'primary', route('employees.index'));
        $add('present_today', 'attendance.view_all', 'Present Today', fn () => AttendanceRecord::whereDate('work_date', today())->whereIn('status', ['present', 'late'])->count(), 'person-check', 'success', route('attendance.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]));

        $issued = fn () => Invoice::whereNotIn('status', ['draft', 'void']);
        $add('total_sales', 'financials.view', 'Vehicle Sales (Revenue)', fn () => Money::sum($issued()->where('type', 'vehicle_sale')->pluck('total')), 'graph-up-arrow', 'success', route('invoices.index', ['type' => 'vehicle_sale']), true);
        $add('repair_revenue', 'financials.view', 'Repair & Service Revenue', fn () => Money::sum($issued()->whereIn('type', ['repair', 'interior', 'detailing', 'parts_sale', 'combined'])->pluck('total')), 'wrench-adjustable', 'info', route('invoices.index'), true);
        $add('total_expenses', 'financials.view', 'Total Expenses', fn () => $this->expensesBetween(null, null)['total'], 'cash-stack', 'danger', route('reports.show', 'expenses'), true);
        $add('receivables', 'financials.view', 'Customer Receivables', fn () => Money::sum(Invoice::open()->pluck('balance')), 'hourglass-split', 'warning', route('reports.show', 'customer_receivables'), true);
        $add('payables', 'financials.view', 'Employee Payables', fn () => Money::sum(PayrollEntry::whereHas('period', fn ($q) => $q->where('status', '!=', 'draft'))->pluck('remaining_payable')), 'person-badge', 'secondary', route('payroll.index'), true);
        $add('monthly_profit', 'financials.view', 'Gross Profit This Month (est.)', fn () => $this->grossProfitBetween(now()->startOfMonth(), now()->endOfMonth()), 'piggy-bank', 'success', route('reports.show', 'monthly_performance'), true);

        return $cards;
    }

    public function charts(User $user): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $labels = $months->map(fn ($m) => $m->format('M Y'))->all();
        $from = $months->first()->copy()->startOfMonth();
        $key = fn ($date) => Carbon::parse($date)->format('M Y');
        $series = function ($rows, string $dateCol, string $valCol) use ($labels, $key) {
            $grouped = collect($rows)->groupBy(fn ($r) => $key($r->{$dateCol}))->map(fn ($g) => Money::sum($g->pluck($valCol)));

            return array_map(fn ($l) => (float) ($grouped[$l] ?? 0), $labels);
        };

        $charts = [];
        if ($user->can('financials.view')) {
            $inv = Invoice::whereNotIn('status', ['draft', 'void'])->where('invoice_date', '>=', $from)->get(['invoice_date', 'total', 'type']);
            $charts['sales'] = ['type' => 'bar', 'title' => 'Monthly Vehicle Sales', 'labels' => $labels,
                'datasets' => [['label' => 'Vehicle sales', 'data' => $series($inv->where('type', 'vehicle_sale'), 'invoice_date', 'total')]]];
            $charts['repair_revenue'] = ['type' => 'line', 'title' => 'Repair & Service Revenue', 'labels' => $labels,
                'datasets' => [['label' => 'Service revenue', 'data' => $series($inv->where('type', '!=', 'vehicle_sale'), 'invoice_date', 'total')]]];

            $aging = $this->receivableAging();
            $charts['outstanding'] = ['type' => 'bar', 'title' => 'Outstanding Payments (aging)', 'labels' => array_keys($aging),
                'datasets' => [['label' => 'Balance', 'data' => array_values($aging)]]];
        }
        if ($user->can('vehicles.view')) {
            $byStatus = Vehicle::whereNotIn('inventory_status', ['archived'])->selectRaw('inventory_status as s, count(*) as c')->groupBy('inventory_status')->pluck('c', 's');
            $charts['inventory'] = ['type' => 'doughnut', 'title' => 'Vehicle Inventory', 'labels' => $byStatus->keys()->map(fn ($s) => status_label($s))->all(),
                'datasets' => [['label' => 'Vehicles', 'data' => $byStatus->values()->all()]]];
            $repair = Vehicle::whereNotIn('inventory_status', ['sold', 'delivered', 'archived'])->selectRaw('repair_status as s, count(*) as c')->groupBy('repair_status')->pluck('c', 's');
            $names = VehicleStatus::options();
            $charts['repair_status'] = ['type' => 'bar', 'title' => 'Vehicle Repair Status', 'labels' => $repair->keys()->map(fn ($s) => $names[$s] ?? status_label($s))->all(),
                'datasets' => [['label' => 'Vehicles', 'data' => $repair->values()->all()]], 'horizontal' => true];
        }
        if ($user->can('financials.view') || $user->can('purchase_orders.view')) {
            $pos = PurchaseOrder::whereNotIn('status', ['draft', 'cancelled'])->where('order_date', '>=', $from)->get(['order_date', 'total']);
            $charts['parts_expenses'] = ['type' => 'bar', 'title' => 'Parts Purchases', 'labels' => $labels,
                'datasets' => [['label' => 'Parts purchased', 'data' => $series($pos, 'order_date', 'total')]]];
        }
        if ($user->can('payroll.view')) {
            $periods = PayrollPeriod::where('status', '!=', 'draft')->where('end_date', '>=', $from)->get(['end_date', 'total_gross', 'total_paid']);
            $charts['payroll'] = ['type' => 'line', 'title' => 'Employee Payroll', 'labels' => $labels, 'datasets' => [
                ['label' => 'Gross payroll', 'data' => $series($periods, 'end_date', 'total_gross')],
                ['label' => 'Paid', 'data' => $series($periods, 'end_date', 'total_paid')],
            ]];
        }

        return $charts;
    }

    public function activity(User $user, int $limit = 12): array
    {
        if (! $user->can('audit_logs.view')) {
            $q = AuditLog::where('user_id', $user->id);
        } else {
            $q = AuditLog::query();
        }

        return $q->with('user:id,name')->whereNotIn('event', ['exported'])->latest('id')->limit($limit)->get()
            ->map(fn ($l) => [
                'event' => $l->event,
                'description' => $l->description,
                'user' => $l->user?->name ?? 'System',
                'time' => $l->created_at->diffForHumans(),
            ])->all();
    }

    public function alerts(User $user): array
    {
        $alerts = [];
        if ($user->can('users.approve') && ($n = User::where('status', 'pending')->count())) {
            $alerts[] = ['level' => 'warning', 'icon' => 'person-plus', 'text' => "$n account(s) awaiting approval", 'url' => route('users.index', ['status' => 'pending'])];
        }
        if ($user->can('parts.view') && ($n = Part::whereIn('stock_status', ['low_stock', 'out_of_stock'])->count())) {
            $alerts[] = ['level' => 'danger', 'icon' => 'box-seam', 'text' => "$n part(s) low or out of stock", 'url' => route('parts.index', ['stock_status' => 'low_stock'])];
        }
        if ($user->can('invoices.view') && ($n = Invoice::where('status', 'overdue')->count())) {
            $alerts[] = ['level' => 'danger', 'icon' => 'receipt', 'text' => "$n overdue invoice(s)", 'url' => route('invoices.index', ['status' => 'overdue'])];
        }
        if ($user->can('vehicles.view')) {
            if ($n = VehicleDocument::whereNotNull('expires_at')->whereDateBetween('expires_at', [today(), today()->addDays(30)])->count()) {
                $alerts[] = ['level' => 'warning', 'icon' => 'file-earmark-text', 'text' => "$n vehicle document(s) expiring within 30 days", 'url' => route('vehicles.index')];
            }
            if ($n = Vehicle::whereNotIn('inventory_status', ['sold', 'delivered', 'archived'])->whereDateBetween('registration_expiry', [today(), today()->addDays(30)])->count()) {
                $alerts[] = ['level' => 'warning', 'icon' => 'card-text', 'text' => "$n vehicle registration(s) expiring within 30 days", 'url' => route('vehicles.index')];
            }
        }
        if ($user->can('payroll.view') && ($n = PayrollEntry::where('payment_status', 'overdue')->count())) {
            $alerts[] = ['level' => 'danger', 'icon' => 'cash', 'text' => "$n overdue payroll payment(s)", 'url' => route('payroll.index')];
        }
        if ($user->can('service_jobs.view')) {
            $n = ServiceJob::visibleTo($user)->where('status', 'on_hold')->count();
            if ($n) {
                $alerts[] = ['level' => 'info', 'icon' => 'pause-circle', 'text' => "$n service job(s) on hold", 'url' => route('service-jobs.index', ['status' => 'on_hold'])];
            }
        }
        if ($employee = $user->employee) {
            if ($n = EmployeeTask::where('employee_id', $employee->id)->whereIn('status', ['open', 'in_progress'])->count()) {
                $alerts[] = ['level' => 'info', 'icon' => 'list-check', 'text' => "You have $n open task(s)", 'url' => route('tasks.index', ['mine' => 1])];
            }
        }

        return $alerts;
    }

    public function receivableAging(): array
    {
        $buckets = ['Current' => 0, '1-30 days' => 0, '31-60 days' => 0, '61-90 days' => 0, '90+ days' => 0];
        foreach (Invoice::open()->get(['due_date', 'balance']) as $inv) {
            $days = $inv->due_date ? (int) $inv->due_date->diffInDays(today(), false) : 0;
            $bucket = match (true) {
                $days <= 0 => 'Current',
                $days <= 30 => '1-30 days',
                $days <= 60 => '31-60 days',
                $days <= 90 => '61-90 days',
                default => '90+ days',
            };
            $buckets[$bucket] = Money::add($buckets[$bucket], $inv->balance);
        }

        return $buckets;
    }

    /**
     * Expenses = parts purchases (POs) + manual vehicle expenses + payroll (approved, gross).
     * Vehicle purchase prices are inventory investment, not expenses.
     */
    public function expensesBetween(?Carbon $from, ?Carbon $to): array
    {
        $range = fn ($q, $col) => $q->when($from, fn ($x) => $x->whereDate($col, '>=', $from))->when($to, fn ($x) => $x->whereDate($col, '<=', $to));
        $parts = Money::sum($range(PurchaseOrder::whereNotIn('status', ['draft', 'cancelled']), 'order_date')->pluck('total'));
        $vehicle = Money::sum($range(VehicleExpense::query(), 'expense_date')->pluck('amount'));
        $payroll = Money::sum($range(PayrollPeriod::where('status', '!=', 'draft'), 'end_date')->pluck('total_gross'));

        return ['parts' => $parts, 'vehicle' => $vehicle, 'payroll' => $payroll, 'total' => Money::add($parts, $vehicle, $payroll)];
    }

    /** Revenue (net of discount, excl. tax) minus captured cost of goods/services. Excludes overheads & payroll. */
    public function grossProfitBetween(Carbon $from, Carbon $to): float
    {
        $rows = Invoice::whereNotIn('status', ['draft', 'void'])->whereDateBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->get(['subtotal', 'discount_amount', 'cost_total']);

        return Money::sum($rows->map(fn ($i) => Money::sub(Money::sub($i->subtotal, $i->discount_amount), $i->cost_total)));
    }

    public function cashCollectedBetween(Carbon $from, Carbon $to): float
    {
        $payments = Payment::completed()->whereDateBetween('payment_date', [$from->toDateString(), $to->toDateString()])->get(['type', 'amount']);

        return Money::sum($payments->map(fn ($p) => $p->signedAmount()));
    }
}
