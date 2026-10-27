<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PurchaseOrder;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Every report returns: columns (key => label), rows, summary (label => value),
 * money (column keys formatted as currency) and an optional chart.
 */
class ReportService
{
    public function __construct(protected DashboardService $dashboard) {}

    public static function definitions(): array
    {
        return [
            'inventory_value' => ['title' => 'Vehicle Inventory Value', 'group' => 'Vehicles', 'financial' => true, 'filters' => ['vehicle']],
            'vehicle_profit' => ['title' => 'Vehicle Profit / Loss', 'group' => 'Vehicles', 'financial' => true, 'filters' => ['date', 'vehicle', 'customer']],
            'vehicle_sales' => ['title' => 'Vehicle Sales', 'group' => 'Sales', 'financial' => true, 'filters' => ['date', 'customer', 'employee']],
            'repair_jobs' => ['title' => 'Repair Jobs', 'group' => 'Service', 'financial' => false, 'filters' => ['date', 'vehicle', 'employee', 'customer']],
            'repair_revenue' => ['title' => 'Repair Revenue', 'group' => 'Service', 'financial' => true, 'filters' => ['date', 'customer', 'vehicle']],
            'revenue_by_service' => ['title' => 'Revenue by Service Type', 'group' => 'Sales', 'financial' => true, 'filters' => ['date']],
            'sales_by_employee' => ['title' => 'Sales by Employee', 'group' => 'Sales', 'financial' => true, 'filters' => ['date']],
            'parts_purchases' => ['title' => 'Parts Purchases', 'group' => 'Parts', 'financial' => true, 'filters' => ['date']],
            'parts_consumption' => ['title' => 'Parts Consumption', 'group' => 'Parts', 'financial' => false, 'filters' => ['date', 'vehicle']],
            'low_stock' => ['title' => 'Low Stock', 'group' => 'Parts', 'financial' => false, 'filters' => []],
            'parts_valuation' => ['title' => 'Parts Inventory Valuation', 'group' => 'Parts', 'financial' => true, 'filters' => []],
            'employee_attendance' => ['title' => 'Employee Attendance', 'group' => 'HR', 'financial' => false, 'filters' => ['date', 'employee', 'department'], 'permission' => 'attendance.view_all'],
            'employee_payroll' => ['title' => 'Employee Payroll', 'group' => 'HR', 'financial' => true, 'filters' => ['date', 'employee', 'department'], 'permission' => 'payroll.view'],
            'customer_receivables' => ['title' => 'Customer Receivables', 'group' => 'Finance', 'financial' => true, 'filters' => ['customer']],
            'supplier_payables' => ['title' => 'Supplier Payables', 'group' => 'Finance', 'financial' => true, 'filters' => []],
            'expenses' => ['title' => 'Daily / Weekly / Monthly Expenses', 'group' => 'Finance', 'financial' => true, 'filters' => ['date', 'period']],
            'monthly_performance' => ['title' => 'Monthly Business Performance', 'group' => 'Finance', 'financial' => true, 'filters' => ['date']],
        ];
    }

    public function run(string $key, Request $r): array
    {
        $from = $r->filled('date_from') ? Carbon::parse($r->date_from)->startOfDay() : now()->subMonths(11)->startOfMonth();
        $to = $r->filled('date_to') ? Carbon::parse($r->date_to)->endOfDay() : now()->endOfDay();
        $method = 'report'.str_replace('_', '', ucwords($key, '_'));

        return $this->{$method}($r, $from, $to) + ['from' => $from, 'to' => $to, 'money' => [], 'chart' => null, 'summary' => []];
    }

    protected function reportInventoryValue(Request $r): array
    {
        $rows = Vehicle::whereNotIn('inventory_status', ['sold', 'delivered', 'archived'])->whereIn('ownership_type', ['company', 'consignment'])
            ->when($r->vehicle_id, fn ($q) => $q->whereKey($r->vehicle_id))->orderBy('stock_number')->get()
            ->map(fn ($v) => [
                'stock' => $v->stock_number, 'vin' => $v->vin, 'vehicle' => $v->title(), 'status' => status_label($v->inventory_status),
                'days' => $v->purchase_date ? (int) $v->purchase_date->diffInDays(today()) : '',
                'purchase' => (float) $v->purchase_price,
                'repairs' => Money::add($v->parts_cost, $v->labor_cost, $v->interior_cost, $v->detailing_cost),
                'other' => (float) $v->other_cost, 'investment' => (float) $v->total_investment,
                'asking' => (float) $v->asking_price, 'potential' => $v->asking_price ? Money::sub($v->asking_price, $v->total_investment) : 0,
            ]);

        return ['columns' => ['stock' => 'Stock #', 'vin' => 'VIN', 'vehicle' => 'Vehicle', 'status' => 'Status', 'days' => 'Days in Stock',
            'purchase' => 'Purchase', 'repairs' => 'Repairs', 'other' => 'Other', 'investment' => 'Total Investment', 'asking' => 'Asking', 'potential' => 'Potential Margin'],
            'rows' => $rows->all(), 'money' => ['purchase', 'repairs', 'other', 'investment', 'asking', 'potential'],
            'summary' => ['Vehicles in stock' => $rows->count(), 'Total investment (inventory value at cost)' => money($rows->sum('investment')),
                'Total asking value' => money($rows->sum('asking')), 'Potential margin (estimate)' => money($rows->sum('potential'))]];
    }

    protected function reportVehicleProfit(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = Vehicle::whereNotNull('sold_at')->whereDateBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->when($r->vehicle_id, fn ($q) => $q->whereKey($r->vehicle_id))
            ->when($r->customer_id, fn ($q) => $q->where('sold_to_customer_id', $r->customer_id))
            ->with('buyer')->orderBy('sold_at')->get()->map(function ($v) {
                $profit = Money::sub($v->sale_price, $v->total_investment);

                return ['date' => fmt_date($v->sold_at), 'stock' => $v->stock_number, 'vehicle' => $v->title(), 'buyer' => $v->buyer?->display_name,
                    'investment' => (float) $v->total_investment, 'sale' => (float) $v->sale_price, 'profit' => $profit,
                    'margin' => (float) $v->sale_price > 0 ? round($profit / (float) $v->sale_price * 100, 1).'%' : '',
                    'paid' => (float) $v->amount_paid, 'balance' => (float) $v->balance_due];
            });

        return ['columns' => ['date' => 'Sold', 'stock' => 'Stock #', 'vehicle' => 'Vehicle', 'buyer' => 'Buyer', 'investment' => 'Investment',
            'sale' => 'Sale Price', 'profit' => 'Profit / Loss', 'margin' => 'Margin', 'paid' => 'Collected', 'balance' => 'Receivable'],
            'rows' => $rows->all(), 'money' => ['investment', 'sale', 'profit', 'paid', 'balance'],
            'summary' => ['Vehicles sold' => $rows->count(), 'Revenue (sale prices)' => money($rows->sum('sale')), 'Cost (investment)' => money($rows->sum('investment')),
                'Gross profit' => money($rows->sum('profit')), 'Cash collected' => money($rows->sum('paid')), 'Still receivable' => money($rows->sum('balance'))]];
    }

    protected function invoicesQuery(Carbon $from, Carbon $to)
    {
        return Invoice::whereNotIn('status', ['draft', 'void'])->whereDateBetween('invoice_date', [$from->toDateString(), $to->toDateString()]);
    }

    protected function reportVehicleSales(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = $this->invoicesQuery($from, $to)->where('type', 'vehicle_sale')
            ->when($r->customer_id, fn ($q) => $q->where('customer_id', $r->customer_id))
            ->when($r->employee_id, fn ($q) => $q->where('sales_rep_id', Employee::find($r->employee_id)?->user_id ?? 0))
            ->with(['vehicle', 'salesRep', 'financing'])->orderBy('invoice_date')->get()
            ->map(fn ($i) => ['date' => fmt_date($i->invoice_date), 'invoice' => $i->invoice_number, 'customer' => $i->customer_name,
                'vehicle' => $i->vehicle?->title(), 'rep' => $i->salesRep?->name, 'total' => (float) $i->total, 'paid' => (float) $i->amount_paid,
                'financed' => $i->pendingFinancedAmount(), 'balance' => (float) $i->balance, 'status' => status_label($i->payment_status)]);

        return ['columns' => ['date' => 'Date', 'invoice' => 'Invoice', 'customer' => 'Customer', 'vehicle' => 'Vehicle', 'rep' => 'Sales Rep',
            'total' => 'Total', 'paid' => 'Collected', 'financed' => 'Pending Lender', 'balance' => 'Balance', 'status' => 'Payment'],
            'rows' => $rows->all(), 'money' => ['total', 'paid', 'financed', 'balance'],
            'summary' => ['Sales' => $rows->count(), 'Revenue' => money($rows->sum('total')), 'Collected cash' => money($rows->sum('paid')),
                'Awaiting lender settlement' => money($rows->sum('financed')), 'Total receivable' => money($rows->sum('balance'))]];
    }

    protected function reportRepairJobs(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = ServiceJob::whereBetween('created_at', [$from, $to])
            ->when($r->vehicle_id, fn ($q) => $q->where('vehicle_id', $r->vehicle_id))
            ->when($r->customer_id, fn ($q) => $q->where('customer_id', $r->customer_id))
            ->when($r->employee_id, fn ($q) => $q->whereHas('employees', fn ($e) => $e->where('employees.id', $r->employee_id)))
            ->with(['vehicle', 'employees', 'customer'])->latest()->get()
            ->map(fn ($j) => ['job' => $j->job_number, 'date' => fmt_date($j->created_at), 'category' => $j->categoryLabel(), 'title' => $j->title,
                'vehicle' => $j->vehicle?->title(), 'customer' => $j->customer?->display_name, 'status' => status_label($j->status),
                'staff' => $j->employees->pluck('full_name')->implode(', '), 'cost' => (float) $j->total_cost, 'charge' => (float) $j->customer_charge]);
        $byStatus = $rows->groupBy('status')->map->count();

        return ['columns' => ['job' => 'Job #', 'date' => 'Created', 'category' => 'Category', 'title' => 'Title', 'vehicle' => 'Vehicle', 'customer' => 'Customer',
            'status' => 'Status', 'staff' => 'Employees', 'cost' => 'Cost', 'charge' => 'Charge'],
            'rows' => $rows->all(), 'money' => ['cost', 'charge'],
            'summary' => ['Jobs' => $rows->count(), 'Total cost' => money($rows->sum('cost')), 'Total charges' => money($rows->sum('charge'))],
            'chart' => ['type' => 'doughnut', 'labels' => $byStatus->keys()->all(), 'datasets' => [['label' => 'Jobs', 'data' => $byStatus->values()->all()]]]];
    }

    protected function reportRepairRevenue(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = $this->invoicesQuery($from, $to)->where('type', '!=', 'vehicle_sale')
            ->when($r->customer_id, fn ($q) => $q->where('customer_id', $r->customer_id))
            ->when($r->vehicle_id, fn ($q) => $q->where('vehicle_id', $r->vehicle_id))
            ->orderBy('invoice_date')->get()
            ->map(fn ($i) => ['date' => fmt_date($i->invoice_date), 'invoice' => $i->invoice_number, 'type' => config('autoshop.enums.invoice_types.'.$i->type),
                'customer' => $i->customer_name, 'revenue' => Money::sub($i->subtotal, $i->discount_amount), 'tax' => (float) $i->tax_amount,
                'cost' => (float) $i->cost_total, 'margin' => Money::sub(Money::sub($i->subtotal, $i->discount_amount), $i->cost_total),
                'paid' => (float) $i->amount_paid, 'balance' => (float) $i->balance]);

        return ['columns' => ['date' => 'Date', 'invoice' => 'Invoice', 'type' => 'Type', 'customer' => 'Customer', 'revenue' => 'Revenue (net)',
            'tax' => 'Tax', 'cost' => 'Cost', 'margin' => 'Gross Margin', 'paid' => 'Collected', 'balance' => 'Receivable'],
            'rows' => $rows->all(), 'money' => ['revenue', 'tax', 'cost', 'margin', 'paid', 'balance'],
            'summary' => ['Invoices' => $rows->count(), 'Revenue (excl. tax)' => money($rows->sum('revenue')), 'Tax collected/charged' => money($rows->sum('tax')),
                'Cost' => money($rows->sum('cost')), 'Gross margin (estimate)' => money($rows->sum('margin')), 'Cash collected' => money($rows->sum('paid')),
                'Receivable' => money($rows->sum('balance'))]];
    }

    protected function reportRevenueByService(Request $r, Carbon $from, Carbon $to): array
    {
        $items = InvoiceItem::whereIn('invoice_id', $this->invoicesQuery($from, $to)->select('id'))->with('serviceJob')->get();
        $groups = $items->groupBy(function ($i) {
            if ($i->item_type === 'service_job' && $i->serviceJob) {
                return $i->serviceJob->categoryLabel();
            }

            return config('autoshop.enums.invoice_item_types.'.$i->item_type, $i->item_type);
        });
        $rows = $groups->map(fn ($g, $k) => ['type' => $k, 'lines' => $g->count(), 'revenue' => Money::sum($g->pluck('line_total')),
            'cost' => Money::sum($g->map(fn ($i) => Money::mul($i->unit_cost, $i->quantity))),
        ])->map(fn ($row) => $row + ['margin' => Money::sub($row['revenue'], $row['cost'])])->sortByDesc('revenue')->values();

        return ['columns' => ['type' => 'Service / Item Type', 'lines' => 'Lines', 'revenue' => 'Revenue (pre-discount, excl. tax)', 'cost' => 'Cost', 'margin' => 'Gross Margin'],
            'rows' => $rows->all(), 'money' => ['revenue', 'cost', 'margin'],
            'summary' => ['Revenue' => money($rows->sum('revenue')), 'Gross margin' => money($rows->sum('margin'))],
            'chart' => ['type' => 'pie', 'labels' => $rows->pluck('type')->all(), 'datasets' => [['label' => 'Revenue', 'data' => $rows->pluck('revenue')->all()]]]];
    }

    protected function reportSalesByEmployee(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = $this->invoicesQuery($from, $to)->with('salesRep')->get()->groupBy(fn ($i) => $i->salesRep?->name ?? 'Unassigned')
            ->map(fn ($g, $name) => ['employee' => $name, 'invoices' => $g->count(), 'vehicles' => $g->where('type', 'vehicle_sale')->count(),
                'revenue' => Money::sum($g->pluck('total')), 'collected' => Money::sum($g->pluck('amount_paid')), 'balance' => Money::sum($g->pluck('balance'))])
            ->sortByDesc('revenue')->values();

        return ['columns' => ['employee' => 'Sales Rep', 'invoices' => 'Invoices', 'vehicles' => 'Vehicles Sold', 'revenue' => 'Revenue', 'collected' => 'Collected', 'balance' => 'Receivable'],
            'rows' => $rows->all(), 'money' => ['revenue', 'collected', 'balance'],
            'summary' => ['Revenue' => money($rows->sum('revenue')), 'Collected' => money($rows->sum('collected'))],
            'chart' => ['type' => 'bar', 'labels' => $rows->pluck('employee')->all(), 'datasets' => [['label' => 'Revenue', 'data' => $rows->pluck('revenue')->all()]]]];
    }

    protected function reportPartsPurchases(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = PurchaseOrder::whereNotIn('status', ['draft', 'cancelled'])->whereDateBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->with('supplier')->orderBy('order_date')->get()
            ->map(fn ($p) => ['date' => fmt_date($p->order_date), 'po' => $p->po_number, 'supplier' => $p->supplier?->name, 'status' => status_label($p->status),
                'total' => (float) $p->total, 'paid' => (float) $p->amount_paid, 'balance' => (float) $p->balance]);

        return ['columns' => ['date' => 'Date', 'po' => 'PO #', 'supplier' => 'Supplier', 'status' => 'Status', 'total' => 'Total', 'paid' => 'Paid', 'balance' => 'Payable'],
            'rows' => $rows->all(), 'money' => ['total', 'paid', 'balance'],
            'summary' => ['Purchase orders' => $rows->count(), 'Purchased' => money($rows->sum('total')), 'Paid' => money($rows->sum('paid')), 'Payable' => money($rows->sum('balance'))]];
    }

    protected function reportPartsConsumption(Request $r, Carbon $from, Carbon $to): array
    {
        $moves = StockMovement::whereIn('type', ['job_issue', 'job_return', 'sale', 'sale_return'])->whereBetween('created_at', [$from, $to])
            ->when($r->vehicle_id, fn ($q) => $q->where('vehicle_id', $r->vehicle_id))->with('part')->get();
        $rows = $moves->groupBy('part_id')->map(function ($g) {
            $p = $g->first()->part;
            $jobs = -1 * $g->whereIn('type', ['job_issue', 'job_return'])->sum('quantity');
            $sales = -1 * $g->whereIn('type', ['sale', 'sale_return'])->sum('quantity');

            return ['part' => $p?->part_number, 'name' => $p?->name, 'jobs' => $jobs, 'sales' => $sales, 'total' => $jobs + $sales,
                'value' => Money::sum($g->map(fn ($m) => Money::mul($m->unit_cost, -1 * $m->quantity)))];
        })->sortByDesc('total')->values();

        return ['columns' => ['part' => 'Part #', 'name' => 'Part', 'jobs' => 'Used in Jobs', 'sales' => 'Sold', 'total' => 'Total Qty', 'value' => 'Cost Value'],
            'rows' => $rows->all(), 'money' => ['value'], 'summary' => ['Parts consumed' => $rows->sum('total'), 'Cost value' => money($rows->sum('value'))]];
    }

    protected function reportLowStock(): array
    {
        $rows = Part::lowStock()->with(['supplier', 'defaultLocation'])->orderBy('quantity')->get()
            ->map(fn ($p) => ['part' => $p->part_number, 'name' => $p->name, 'status' => status_label($p->stock_status), 'on_hand' => $p->quantity,
                'reserved' => $p->quantity_reserved, 'available' => $p->quantity_available, 'min' => $p->min_stock_level,
                'reorder' => max(0, $p->min_stock_level * 2 - $p->quantity_available), 'supplier' => $p->supplier?->name, 'location' => $p->defaultLocation?->name]);

        return ['columns' => ['part' => 'Part #', 'name' => 'Part', 'status' => 'Status', 'on_hand' => 'On Hand', 'reserved' => 'Reserved', 'available' => 'Available',
            'min' => 'Min Level', 'reorder' => 'Suggested Reorder', 'supplier' => 'Supplier', 'location' => 'Location'],
            'rows' => $rows->all(), 'summary' => ['Parts needing attention' => $rows->count()]];
    }

    protected function reportPartsValuation(): array
    {
        $rows = Part::with('category')->orderBy('name')->get()->map(fn ($p) => ['part' => $p->part_number, 'name' => $p->name, 'category' => $p->category?->name,
            'qty' => $p->quantity, 'cost' => (float) $p->purchase_cost, 'value' => $p->stockValue(), 'retail' => Money::mul($p->selling_price, $p->quantity)]);

        return ['columns' => ['part' => 'Part #', 'name' => 'Part', 'category' => 'Category', 'qty' => 'Qty', 'cost' => 'Unit Cost', 'value' => 'Value at Cost', 'retail' => 'Value at Retail'],
            'rows' => $rows->all(), 'money' => ['cost', 'value', 'retail'],
            'summary' => ['Inventory value at cost' => money($rows->sum('value')), 'Inventory value at retail' => money($rows->sum('retail'))]];
    }

    protected function reportEmployeeAttendance(Request $r, Carbon $from, Carbon $to): array
    {
        $records = AttendanceRecord::whereDateBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->when($r->employee_id, fn ($q) => $q->where('employee_id', $r->employee_id))
            ->when($r->department_id, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $r->department_id)))
            ->with('employee.department')->get();
        $rows = $records->groupBy('employee_id')->map(function ($g) {
            $e = $g->first()->employee;

            return ['employee' => $e?->full_name, 'department' => $e?->department?->name, 'present' => $g->where('status', 'present')->count(),
                'late' => $g->where('status', 'late')->count(), 'absent' => $g->where('status', 'absent')->count(), 'leave' => $g->where('status', 'leave')->count(),
                'holiday' => $g->where('status', 'holiday')->count(), 'hours' => round((float) $g->sum('total_hours'), 2), 'overtime' => round((float) $g->sum('overtime_hours'), 2)];
        })->sortBy('employee')->values();

        return ['columns' => ['employee' => 'Employee', 'department' => 'Department', 'present' => 'Present', 'late' => 'Late', 'absent' => 'Absent',
            'leave' => 'Leave', 'holiday' => 'Holiday', 'hours' => 'Total Hours', 'overtime' => 'Overtime'],
            'rows' => $rows->all(), 'summary' => ['Total hours' => $rows->sum('hours'), 'Overtime hours' => $rows->sum('overtime')]];
    }

    protected function reportEmployeePayroll(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = PayrollEntry::whereHas('period', fn ($q) => $q->whereDateBetween('end_date', [$from->toDateString(), $to->toDateString()]))
            ->when($r->employee_id, fn ($q) => $q->where('employee_id', $r->employee_id))
            ->when($r->department_id, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $r->department_id)))
            ->with(['employee', 'period'])->get()
            ->map(fn ($e) => ['employee' => $e->employee?->full_name, 'period' => $e->period->name, 'status' => status_label($e->period->status),
                'hours' => (float) $e->regular_hours, 'ot' => (float) $e->overtime_hours, 'gross' => (float) $e->gross_earnings,
                'deductions' => Money::add($e->deductions, $e->advances), 'net' => (float) $e->net_pay, 'paid' => (float) $e->amount_paid,
                'remaining' => (float) $e->remaining_payable, 'payment' => status_label($e->payment_status)]);

        return ['columns' => ['employee' => 'Employee', 'period' => 'Period', 'status' => 'Period Status', 'hours' => 'Reg. Hours', 'ot' => 'OT Hours',
            'gross' => 'Gross', 'deductions' => 'Deductions + Advances', 'net' => 'Net', 'paid' => 'Paid', 'remaining' => 'Remaining', 'payment' => 'Payment'],
            'rows' => $rows->all(), 'money' => ['gross', 'deductions', 'net', 'paid', 'remaining'],
            'summary' => ['Gross' => money($rows->sum('gross')), 'Net' => money($rows->sum('net')), 'Paid' => money($rows->sum('paid')), 'Payable' => money($rows->sum('remaining'))]];
    }

    protected function reportCustomerReceivables(Request $r): array
    {
        $invoices = Invoice::open()->when($r->customer_id, fn ($q) => $q->where('customer_id', $r->customer_id))->with('financing')->get();
        $rows = $invoices->groupBy('customer_id')->map(function ($g) {
            $buckets = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90p' => 0];
            foreach ($g as $inv) {
                $days = $inv->due_date ? (int) $inv->due_date->diffInDays(today(), false) : 0;
                $k = $days <= 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'd90p')));
                $buckets[$k] = Money::add($buckets[$k], $inv->balance);
            }
            $lender = Money::sum($g->map(fn ($i) => $i->pendingFinancedAmount()));
            $total = Money::sum($g->pluck('balance'));

            return ['customer' => $g->first()->customer_name, 'invoices' => $g->count(), 'total' => $total, 'lender' => $lender,
                'customer_due' => Money::sub($total, $lender), 'current' => $buckets['current'], 'd30' => $buckets['d30'],
                'd60' => $buckets['d60'], 'd90' => $buckets['d90'], 'd90p' => $buckets['d90p']];
        })->sortByDesc('total')->values();

        return ['columns' => ['customer' => 'Customer', 'invoices' => 'Open Invoices', 'total' => 'Total Balance', 'lender' => 'Due from Lender',
            'customer_due' => 'Due from Customer', 'current' => 'Current', 'd30' => '1-30', 'd60' => '31-60', 'd90' => '61-90', 'd90p' => '90+'],
            'rows' => $rows->all(), 'money' => ['total', 'lender', 'customer_due', 'current', 'd30', 'd60', 'd90', 'd90p'],
            'summary' => ['Total receivables' => money($rows->sum('total')), 'Due from lenders (financing pending)' => money($rows->sum('lender')),
                'Due from customers' => money($rows->sum('customer_due'))]];
    }

    protected function reportSupplierPayables(): array
    {
        $rows = Supplier::with('purchaseOrders')->get()->map(fn ($s) => [
            'supplier' => $s->name, 'orders' => $s->purchaseOrders->whereNotIn('status', ['draft', 'cancelled'])->count(),
            'purchased' => Money::sum($s->purchaseOrders->whereNotIn('status', ['draft', 'cancelled'])->pluck('total')),
            'paid' => Money::sum($s->purchaseOrders->whereNotIn('status', ['draft', 'cancelled'])->pluck('amount_paid')),
            'balance' => Money::sum($s->purchaseOrders->whereNotIn('status', ['draft', 'cancelled'])->pluck('balance')),
            'overdue' => Money::sum($s->purchaseOrders->whereNotIn('status', ['draft', 'cancelled'])->filter(fn ($p) => $p->due_date && $p->due_date->isPast())->pluck('balance')),
        ])->filter(fn ($r) => $r['orders'] > 0)->sortByDesc('balance')->values();

        return ['columns' => ['supplier' => 'Supplier', 'orders' => 'Orders', 'purchased' => 'Purchased', 'paid' => 'Paid', 'balance' => 'Payable', 'overdue' => 'Overdue'],
            'rows' => $rows->all(), 'money' => ['purchased', 'paid', 'balance', 'overdue'],
            'summary' => ['Total payable' => money($rows->sum('balance')), 'Overdue' => money($rows->sum('overdue'))]];
    }

    protected function reportExpenses(Request $r, Carbon $from, Carbon $to): array
    {
        $period = in_array($r->period, ['daily', 'weekly', 'monthly'], true) ? $r->period : 'monthly';
        $bucket = fn ($d) => match ($period) {
            'daily' => Carbon::parse($d)->format('Y-m-d'),
            'weekly' => Carbon::parse($d)->startOfWeek()->format('Y-m-d').' (wk)',
            default => Carbon::parse($d)->format('Y-m'),
        };
        $rows = [];
        $add = function ($date, $col, $amt) use (&$rows, $bucket) {
            $k = $bucket($date);
            $rows[$k] ??= ['period' => $k, 'parts' => 0, 'vehicle' => 0, 'payroll' => 0, 'total' => 0];
            $rows[$k][$col] = Money::add($rows[$k][$col], $amt);
            $rows[$k]['total'] = Money::add($rows[$k]['total'], $amt);
        };
        foreach (PurchaseOrder::whereNotIn('status', ['draft', 'cancelled'])->whereDateBetween('order_date', [$from->toDateString(), $to->toDateString()])->get() as $p) {
            $add($p->order_date, 'parts', $p->total);
        }
        foreach (VehicleExpense::whereDateBetween('expense_date', [$from->toDateString(), $to->toDateString()])->get() as $e) {
            $add($e->expense_date, 'vehicle', $e->amount);
        }
        foreach (PayrollPeriod::where('status', '!=', 'draft')->whereDateBetween('end_date', [$from->toDateString(), $to->toDateString()])->get() as $p) {
            $add($p->end_date, 'payroll', $p->total_gross);
        }
        ksort($rows);
        $rows = array_values($rows);

        return ['columns' => ['period' => ucfirst($period).' period', 'parts' => 'Parts Purchases', 'vehicle' => 'Vehicle Expenses', 'payroll' => 'Payroll (gross)', 'total' => 'Total'],
            'rows' => $rows, 'money' => ['parts', 'vehicle', 'payroll', 'total'],
            'summary' => ['Total expenses' => money(collect($rows)->sum('total')), 'Note' => 'Vehicle purchase prices are inventory investment and are not included.'],
            'chart' => ['type' => 'bar', 'stacked' => true, 'labels' => array_column($rows, 'period'), 'datasets' => [
                ['label' => 'Parts', 'data' => array_column($rows, 'parts')], ['label' => 'Vehicle', 'data' => array_column($rows, 'vehicle')],
                ['label' => 'Payroll', 'data' => array_column($rows, 'payroll')]]]];
    }

    protected function reportMonthlyPerformance(Request $r, Carbon $from, Carbon $to): array
    {
        $rows = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $mStart = $cursor->copy()->startOfMonth();
            $mEnd = $cursor->copy()->endOfMonth();
            $inv = Invoice::whereNotIn('status', ['draft', 'void'])->whereDateBetween('invoice_date', [$mStart->toDateString(), $mEnd->toDateString()])->get();
            $revenue = Money::sum($inv->map(fn ($i) => Money::sub($i->subtotal, $i->discount_amount)));
            $expenses = $this->dashboard->expensesBetween($mStart, $mEnd)['total'];
            $rows[] = [
                'month' => $cursor->format('M Y'),
                'revenue' => $revenue,
                'cash' => $this->dashboard->cashCollectedBetween($mStart, $mEnd),
                'gross_profit' => $this->dashboard->grossProfitBetween($mStart, $mEnd),
                'expenses' => $expenses,
                'receivables' => Money::sum($inv->pluck('balance')),
            ];
            $cursor->addMonth();
        }

        return ['columns' => ['month' => 'Month', 'revenue' => 'Revenue (invoiced, excl. tax)', 'cash' => 'Cash Collected (net of refunds)',
            'gross_profit' => 'Gross Profit (est.)', 'expenses' => 'Expenses (parts, vehicle, payroll)', 'receivables' => 'Still Receivable (from month invoices)'],
            'rows' => $rows, 'money' => ['revenue', 'cash', 'gross_profit', 'expenses', 'receivables'],
            'summary' => ['Revenue' => money(collect($rows)->sum('revenue')), 'Cash collected' => money(collect($rows)->sum('cash')),
                'Gross profit (estimate)' => money(collect($rows)->sum('gross_profit')), 'Expenses' => money(collect($rows)->sum('expenses')),
                'Note' => 'Revenue is invoiced (accrual); cash is actual receipts. Gross profit = revenue − captured cost of vehicles, parts and job costs; it excludes payroll and overheads, so it is an estimate.'],
            'chart' => ['type' => 'line', 'labels' => array_column($rows, 'month'), 'datasets' => [
                ['label' => 'Revenue', 'data' => array_column($rows, 'revenue')], ['label' => 'Cash collected', 'data' => array_column($rows, 'cash')],
                ['label' => 'Expenses', 'data' => array_column($rows, 'expenses')], ['label' => 'Gross profit', 'data' => array_column($rows, 'gross_profit')]]]];
    }
}
