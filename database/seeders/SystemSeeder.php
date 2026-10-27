<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\PartCategory;
use App\Models\ServiceType;
use App\Models\StockLocation;
use App\Models\VehicleStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class SystemSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('autoshop:sync-permissions');

        $statuses = [
            ['not_started', 'Not Started', 'secondary'], ['waiting_for_parts', 'Waiting for Parts', 'warning'],
            ['under_inspection', 'Under Inspection', 'info'], ['under_repair', 'Under Repair', 'primary'],
            ['repair_completed', 'Repair Completed', 'success'], ['quality_inspection', 'Quality Inspection', 'purple'],
            ['ready_for_dispatch', 'Ready for Dispatch', 'teal'], ['dispatched', 'Dispatched', 'dark'], ['cancelled', 'Cancelled', 'danger'],
        ];
        foreach ($statuses as $i => [$slug, $name, $color]) {
            VehicleStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'color' => $color, 'sort_order' => $i + 1, 'is_system' => true]);
        }

        StockLocation::firstOrCreate(['name' => 'Main Warehouse'], ['is_default' => true]);

        foreach (['Sales', 'Service / Workshop', 'Parts', 'Detailing', 'Administration', 'Finance'] as $d) {
            Department::firstOrCreate(['name' => $d]);
        }
        foreach (['Engine', 'Brakes', 'Suspension', 'Electrical', 'Body', 'Interior', 'Filters', 'Fluids & Oils', 'Tires & Wheels', 'Cooling', 'Exhaust', 'Transmission', 'Lighting', 'Detailing Supplies'] as $c) {
            PartCategory::firstOrCreate(['name' => $c]);
        }

        // Service catalogue (names only; prices are set by the business in Settings > Lookups).
        $types = [
            'interior' => ['Seat Repair', 'Seat Replacement', 'Upholstery', 'Roof Liner Repair', 'Dashboard Repair', 'Carpet Replacement',
                'Door Panel Repair', 'Interior Trim Repair', 'Other Interior Services'],
            'cleaning' => ['Exterior Wash', 'Interior Cleaning', 'Full Detailing', 'Deep Cleaning', 'Carpet Shampoo', 'Seat Shampoo',
                'Polishing', 'Waxing', 'Engine Bay Cleaning', 'Other'],
            'repair' => ['Diagnosis', 'General Repair', 'Engine Repair', 'Brake Service', 'Suspension Repair', 'Electrical Repair',
                'Body Work', 'Paint', 'Oil Change', 'Tire Service', 'Other Repair'],
        ];
        foreach ($types as $category => $names) {
            foreach ($names as $name) {
                ServiceType::firstOrCreate(['category' => $category, 'name' => $name]);
            }
        }
    }
}
