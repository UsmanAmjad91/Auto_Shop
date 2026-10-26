<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehicleStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $over = []): array
    {
        return array_merge([
            'vin' => '1HGCM82633A004352', 'make' => 'Honda', 'model' => 'Accord', 'year' => 2003, 'body_type' => 'Sedan',
            'ownership_type' => 'company', 'purchase_price' => 5000, 'asking_price' => 7500, 'repair_status' => 'not_started', 'inventory_status' => 'available',
        ], $over);
    }

    public function test_create_vehicle_with_images_generates_stock_number_and_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('vehicles.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('front.jpg', 800, 600)],
            'documents' => [UploadedFile::fake()->create('title.pdf', 120, 'application/pdf')],
        ]));
        $vehicle = Vehicle::first();
        $res->assertRedirect(route('vehicles.show', $vehicle));

        $this->assertStringStartsWith('STK-', $vehicle->stock_number);
        $this->assertEquals(5000, $vehicle->total_investment);
        $this->assertCount(1, $vehicle->images);
        $this->assertTrue($vehicle->images->first()->is_primary);
        Storage::disk('public')->assertExists($vehicle->images->first()->path);
        Storage::disk('local')->assertExists($vehicle->documents->first()->path); // private disk
        $this->assertSame(1, VehicleStatusHistory::where('vehicle_id', $vehicle->id)->count());
        $this->actingAs($admin)->get(route('vehicles.show', $vehicle))->assertOk()->assertSee('1HGCM82633A004352');
    }

    public function test_vin_validation_and_duplicates(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload(['vin' => 'SHORTVIN']))->assertSessionHasErrors('vin');
        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload(['vin' => '1HGCM82633A00435O']))->assertSessionHasErrors('vin');
        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload());
        $this->actingAs($admin)->post(route('vehicles.store'), $this->payload(['stock_number' => 'X1']))->assertSessionHasErrors('vin');
    }

    public function test_rejects_non_image_upload(): void
    {
        $this->actingAs($this->admin())->post(route('vehicles.store'), $this->payload([
            'images' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')],
        ]))->assertSessionHasErrors('images.0');
    }

    public function test_list_filters_sorting_and_pagination(): void
    {
        $admin = $this->admin();
        $this->vehicle(['make' => 'Toyota', 'year' => 2020, 'asking_price' => 20000]);
        $this->vehicle(['make' => 'Toyota', 'year' => 2015, 'asking_price' => 9000]);
        $this->vehicle(['make' => 'Ford', 'year' => 2018, 'asking_price' => 15000, 'repair_status' => 'under_repair']);

        $r = $this->actingAs($admin)->getJson(route('vehicles.index', ['make' => 'Toyota', 'sort' => 'asking_price', 'dir' => 'asc']))->assertOk();
        $this->assertSame(2, $r->json('meta.total'));
        $this->assertEquals(9000, $r->json('data.0.asking_price'));

        $this->assertSame(1, $this->actingAs($admin)->getJson(route('vehicles.index', ['repair_status' => 'under_repair']))->json('meta.total'));
        $this->assertSame(2, $this->actingAs($admin)->getJson(route('vehicles.index', ['year_from' => 2016]))->json('meta.total'));
        $this->assertSame(1, $this->actingAs($admin)->getJson(route('vehicles.index', ['asking_min' => 10000, 'asking_max' => 16000]))->json('meta.total'));
        $r = $this->actingAs($admin)->getJson(route('vehicles.index', ['per_page' => 10, 'page' => 1]));
        $this->assertSame(10, $r->json('meta.per_page'));
    }

    public function test_csv_export(): void
    {
        $this->vehicle(['make' => 'Mazda']);
        $res = $this->actingAs($this->admin())->get(route('vehicles.export', ['format' => 'csv']));
        $res->assertOk();
        $this->assertStringContainsString('Mazda', $res->streamedContent());
    }

    public function test_vin_decode_uses_nhtsa_and_never_fabricates(): void
    {
        Http::fake([
            'vpic.nhtsa.dot.gov/*' => Http::sequence()->push(['Results' => [[
                'Make' => 'HONDA', 'Model' => 'Accord', 'ModelYear' => '2003', 'Trim' => 'EX', 'BodyClass' => 'Coupe', 'FuelTypePrimary' => 'Gasoline',
                'DisplacementL' => '3.0', 'EngineCylinders' => '6', 'EngineConfiguration' => 'V-Shaped', 'TransmissionStyle' => 'Automatic', 'ErrorCode' => '0',
            ]]])->push('error', 500),
        ]);
        $admin = $this->admin();
        $r = $this->actingAs($admin)->postJson(route('vin.decode'), ['vin' => '1HGCM82633A004352'])->assertOk();
        $r->assertJsonPath('success', true)->assertJsonPath('data.make', 'Honda')->assertJsonPath('data.year', 2003)->assertJsonPath('data.body_type', 'Coupe');

        $this->actingAs($admin)->postJson(route('vin.decode'), ['vin' => '1HGCM82633A004352'])->assertOk()
            ->assertJsonPath('success', false)->assertJsonPath('data', []);

        $this->actingAs($admin)->postJson(route('vin.decode'), ['vin' => 'ABC'])->assertStatus(422);
    }

    public function test_bulk_repair_status_records_history(): void
    {
        $admin = $this->admin();
        $a = $this->vehicle();
        $b = $this->vehicle();
        $this->actingAs($admin)->postJson(route('vehicles.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'repair_status', 'value' => 'under_inspection'])->assertOk();
        $this->assertSame('under_inspection', $a->fresh()->repair_status);
        $this->assertSame(2, VehicleStatusHistory::where('to_status', 'under_inspection')->count());
    }

    public function test_expense_updates_total_investment(): void
    {
        $admin = $this->admin();
        $v = $this->vehicle(['purchase_price' => 1000]);
        $this->actingAs($admin)->post(route('vehicles.expenses.store', $v), ['category' => 'transport', 'amount' => 150.25, 'expense_date' => today()->toDateString()]);
        $this->assertEquals(1150.25, $v->fresh()->total_investment);
    }

    public function test_global_search_finds_vehicle_by_vin(): void
    {
        $v = $this->vehicle();
        $this->actingAs($this->admin())->getJson(route('search', ['q' => substr($v->vin, 0, 8)]))->assertOk()->assertJsonPath('results.Vehicles.0.title', $v->title());
    }
}
