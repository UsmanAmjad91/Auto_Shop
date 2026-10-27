<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\ServiceType;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AttendanceService;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ServiceJobService;
use App\Services\VehicleCostService;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * OPTIONAL DEMONSTRATION DATA — every record is labelled "[DEMO]".
 * Never run in production: php artisan db:seed --class=DemoDataSeeder
 * All stock / job / invoice data is created through the real services so the
 * demo exercises the same business rules as production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed demo data in production.');

            return;
        }
        $this->call(SystemSeeder::class);
        auth()->loginUsingId(User::role('Super Administrator')->value('id') ?? 0);

        $inventory = app(InventoryService::class);
        $location = StockLocation::default();

        $suppliers = collect([
            ['[DEMO] Metro Auto Parts', 'Dana Lee'], ['[DEMO] Prime Auctions', 'Sam Ortiz'], ['[DEMO] Detail Supply Co.', 'Kim Park'],
        ])->map(fn ($s, $i) => Supplier::firstOrCreate(['code' => 'DEMO-SUP-'.($i + 1)], ['name' => $s[0], 'contact_person' => $s[1],
            'email' => 'supplier'.($i + 1).'@example.com', 'phone' => '+1 555 010 00'.$i, 'is_active' => true]));

        $customers = collect([
            ['Alex', 'Morgan'], ['Jordan', 'Rivera'], ['Taylor', 'Nguyen'], ['Casey', 'Patel'], ['Riley', 'Kim'],
        ])->map(fn ($c, $i) => Customer::firstOrCreate(['code' => 'DEMO-CUS-'.($i + 1)], ['type' => 'individual', 'first_name' => $c[0],
            'last_name' => $c[1].' [DEMO]', 'email' => 'customer'.($i + 1).'@example.com', 'phone' => '+1 555 020 00'.$i, 'city' => 'Springfield', 'is_active' => true]));

        $workshop = Department::where('name', 'Service / Workshop')->first();
        $employees = collect([
            ['Chris', 'Tech', 'Lead Technician', 'hourly', 28], ['Pat', 'Wrench', 'Technician', 'hourly', 22],
            ['Robin', 'Shine', 'Detailer', 'daily', 160], ['Morgan', 'Sales', 'Sales Associate', 'monthly', 3500],
        ])->map(function ($e, $i) use ($workshop) {
            $emp = Employee::firstOrCreate(['employee_code' => 'DEMO-EMP-'.($i + 1)], ['first_name' => $e[0], 'last_name' => $e[1].' [DEMO]',
                'job_title' => $e[2], 'department_id' => $workshop?->id, 'hire_date' => now()->subYears(2), 'employment_status' => 'active', 'employment_type' => 'full_time']);
            if (! $emp->payRates()->exists()) {
                $emp->payRates()->create(['pay_type' => $e[3], 'rate' => $e[4], 'overtime_multiplier' => 1.5, 'effective_from' => now()->subYears(2)]);
            }

            return $emp;
        });

        $partsData = [
            ['Brake Pad Set (Front)', 'Brakes', 'BP-1001', 35, 89, 12], ['Oil Filter', 'Filters', 'OF-2002', 4.5, 12.99, 40],
            ['Engine Oil 5W-30 (5 qt)', 'Fluids & Oils', 'EO-3003', 18, 39.99, 3], ['Cabin Air Filter', 'Filters', 'CF-4004', 6, 19.99, 0],
            ['Seat Cover Material (yd)', 'Interior', 'SC-5005', 22, 45, 15], ['Ceramic Wax', 'Detailing Supplies', 'CW-6006', 11, 0, 8],
        ];
        $parts = collect($partsData)->map(function ($p, $i) use ($inventory, $location, $suppliers) {
            $part = Part::firstOrCreate(['sku' => 'DEMO-'.$p[2]], ['part_number' => 'DEMO-PRT-'.($i + 1), 'name' => '[DEMO] '.$p[0],
                'category_id' => PartCategory::where('name', $p[1])->value('id'), 'oem_number' => $p[2], 'purchase_cost' => $p[3], 'selling_price' => $p[4],
                'min_stock_level' => 5, 'unit' => 'pcs', 'condition' => 'new', 'default_location_id' => $location->id,
                'supplier_id' => $suppliers[0]->id, 'stock_status' => 'out_of_stock']);
            if ($part->wasRecentlyCreated && $p[5] > 0) {
                $inventory->add($part, $p[5], ['type' => 'purchase', 'location_id' => $location->id, 'notes' => '[DEMO] opening stock']);
            }

            return $part;
        });

        $vehiclesData = [
            ['Toyota', 'Camry', 2019, 'Sedan', 'company', 14500, 19900], ['Honda', 'CR-V', 2020, 'SUV', 'company', 18200, 24500],
            ['Ford', 'F-150', 2018, 'Truck', 'company', 21000, 28900], ['Nissan', 'Altima', 2017, 'Sedan', 'customer', 0, null],
            ['Chevrolet', 'Express', 2016, 'Van', 'consignment', 0, 15900],
        ];
        $costs = app(VehicleCostService::class);
        $vehicles = collect($vehiclesData)->map(function ($v, $i) use ($customers, $suppliers, $costs) {
            $vehicle = Vehicle::firstOrCreate(['stock_number' => 'DEMO-STK-'.($i + 1)], [
                'vin' => VehicleFactory::validVin(), 'make' => $v[0], 'model' => $v[1], 'year' => $v[2], 'body_type' => $v[3],
                'ownership_type' => $v[4], 'purchase_price' => $v[5], 'asking_price' => $v[6], 'mileage' => 40000 + $i * 12000,
                'customer_id' => $v[4] === 'customer' ? $customers[3]->id : null, 'supplier_id' => $v[4] === 'company' ? $suppliers[1]->id : null,
                'purchase_date' => now()->subDays(60 - $i * 7), 'purchase_source' => 'auction', 'condition' => 'good',
                'repair_status' => 'not_started', 'inventory_status' => 'available', 'payment_status' => 'unpaid',
                'notes' => '[DEMO] sample vehicle', 'exterior_color' => ['White', 'Silver', 'Black', 'Blue', 'Gray'][$i],
            ]);

            return $costs->recalculate($vehicle);
        });

        if ($vehicles[0]->wasRecentlyCreated) {
            $jobs = app(ServiceJobService::class);
            $repair = $jobs->create(['category' => 'repair', 'vehicle_id' => $vehicles[0]->id, 'title' => '[DEMO] Brake service', 'priority' => 'normal',
                'is_billable' => false, 'supervisor_id' => $employees[0]->id], [$employees[0]->id => ['role' => 'lead'], $employees[1]->id => ['role' => 'technician']]);
            $jobs->addItem($repair, ['item_type' => 'part', 'part_id' => $parts[0]->id, 'quantity' => 1]);
            $jobs->addItem($repair, ['item_type' => 'labor', 'description' => 'Brake labor (2h)', 'quantity' => 2, 'unit_cost' => 28, 'unit_price' => 95]);
            $jobs->changeStatus($repair, 'in_progress');
            $jobs->changeStatus($repair, 'completed');

            $detail = $jobs->create(['category' => 'cleaning', 'vehicle_id' => $vehicles[1]->id, 'title' => '[DEMO] Full detailing', 'priority' => 'normal',
                'is_billable' => false], [$employees[2]->id => ['role' => 'detailer']]);
            $type = ServiceType::where('category', 'cleaning')->where('name', 'Full Detailing')->first();
            $jobs->addItem($detail, ['item_type' => 'service', 'service_type_id' => $type?->id, 'description' => 'Full Detailing', 'quantity' => 1, 'unit_cost' => 60, 'unit_price' => 0]);
            $jobs->addItem($detail, ['item_type' => 'part', 'part_id' => $parts[5]->id, 'quantity' => 1]);
            $jobs->changeStatus($detail, 'in_progress');

            $customerJob = $jobs->create(['category' => 'repair', 'vehicle_id' => $vehicles[3]->id, 'customer_id' => $customers[3]->id,
                'title' => '[DEMO] Oil change', 'priority' => 'normal', 'is_billable' => true], [$employees[1]->id => ['role' => 'technician']]);
            $jobs->addItem($customerJob, ['item_type' => 'part', 'part_id' => $parts[1]->id, 'quantity' => 1]);
            $jobs->addItem($customerJob, ['item_type' => 'part', 'part_id' => $parts[2]->id, 'quantity' => 1]);
            $jobs->addItem($customerJob, ['item_type' => 'labor', 'description' => 'Oil change labor', 'quantity' => 0.5, 'unit_cost' => 22, 'unit_price' => 80]);
            $jobs->changeStatus($customerJob, 'completed');

            $invoices = app(InvoiceService::class);
            $repairInvoice = $invoices->create(['type' => 'repair', 'customer_id' => $customers[3]->id, 'vehicle_id' => $vehicles[3]->id,
                'tax_rate' => 8, 'notes' => '[DEMO] invoice'], $invoices->itemsFromJob($customerJob->fresh('items')), true);
            app(PaymentService::class)->record($customers[3], ['amount' => 50, 'method' => 'cash', 'payment_date' => today()->toDateString()], [$repairInvoice->id => 50]);

            $sale = $invoices->create(['type' => 'vehicle_sale', 'customer_id' => $customers[0]->id, 'vehicle_id' => $vehicles[2]->id, 'tax_rate' => 0,
                'notes' => '[DEMO] vehicle sale with lender financing'], [['item_type' => 'vehicle', 'vehicle_id' => $vehicles[2]->id, 'unit_price' => 28500]], true);
            app(PaymentService::class)->record($customers[0], ['amount' => 5000, 'method' => 'bank_transfer', 'type' => 'deposit',
                'payment_date' => today()->toDateString(), 'reference' => 'DEMO-DOWN'], [$sale->id => 5000]);
            app(PaymentService::class)->saveFinancing($sale->fresh(), ['lender_name' => '[DEMO] First Auto Credit', 'financed_amount' => 23500,
                'down_payment' => 5000, 'status' => 'approved', 'term_months' => 60, 'interest_rate' => 6.9]);
        }

        // Two weeks of attendance for hourly staff.
        $att = app(AttendanceService::class);
        foreach ($employees->take(3) as $emp) {
            for ($d = 14; $d >= 1; $d--) {
                $day = Carbon::today()->subDays($d);
                if ($day->isWeekend() || AttendanceRecord::where('employee_id', $emp->id)->whereDate('work_date', $day)->exists()) {
                    continue;
                }
                $att->saveManual(['employee_id' => $emp->id, 'work_date' => $day->toDateString(), 'clock_in' => '08:0'.random_int(0, 9),
                    'clock_out' => random_int(0, 3) ? '17:00' : '18:30', 'break_minutes' => 30, 'status' => 'present', 'notes' => '[DEMO]']);
            }
        }

        auth()->logout();
        $this->command?->info('Demo data created (all labelled [DEMO]).');
    }
}
