<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InventoryService;
use App\Services\VehicleCostService;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedSystem = true;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->seedSystem && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->seed(SystemSeeder::class);
        }
        $this->withoutVite();
    }

    protected function userWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    protected function admin(): User
    {
        return $this->userWithRole('Super Administrator');
    }

    protected function partWithStock(int $qty = 10, array $attrs = []): Part
    {
        $part = Part::factory()->create($attrs + ['purchase_cost' => 20, 'selling_price' => 50]);
        if ($qty > 0) {
            app(InventoryService::class)->add($part, $qty);
        }

        return $part->fresh();
    }

    protected function vehicle(array $attrs = []): Vehicle
    {
        return app(VehicleCostService::class)->recalculate(Vehicle::factory()->create($attrs));
    }

    protected function customer(array $attrs = []): Customer
    {
        return Customer::factory()->create($attrs);
    }
}
