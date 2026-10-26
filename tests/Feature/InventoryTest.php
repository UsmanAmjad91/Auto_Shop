<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartStock;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_remove_and_status(): void
    {
        $part = $this->partWithStock(10, ['min_stock_level' => 3]);
        $this->assertSame('available', $part->stock_status);
        app(InventoryService::class)->remove($part, 8);
        $this->assertSame(2, $part->fresh()->quantity);
        $this->assertSame('low_stock', $part->fresh()->stock_status);
        $this->assertSame(2, StockMovement::where('part_id', $part->id)->count());
    }

    public function test_negative_stock_is_prevented(): void
    {
        $part = $this->partWithStock(2);
        $this->expectException(ValidationException::class);
        app(InventoryService::class)->remove($part, 3);
    }

    public function test_negative_stock_allowed_only_with_override(): void
    {
        $part = $this->partWithStock(1);
        app(InventoryService::class)->remove($part, 3, ['allow_negative' => true]);
        $this->assertSame(-2, $part->fresh()->quantity);
    }

    public function test_reservations_reduce_available_stock(): void
    {
        $inv = app(InventoryService::class);
        $part = $this->partWithStock(5);
        $inv->reserve($part, 4);
        $this->assertSame(1, $part->fresh()->quantity_available);
        try {
            $inv->remove($part->fresh(), 2);
            $this->fail('Should not allow consuming reserved stock');
        } catch (ValidationException) {
        }
        $inv->unreserve($part->fresh(), 4);
        $inv->remove($part->fresh(), 2);
        $this->assertSame(3, $part->fresh()->quantity);
    }

    public function test_transfer_between_locations(): void
    {
        $part = $this->partWithStock(6);
        $from = StockLocation::default();
        $to = StockLocation::create(['name' => 'Annex']);
        app(InventoryService::class)->transfer($part, $from->id, $to->id, 4);
        $this->assertSame(2, PartStock::where('part_id', $part->id)->where('location_id', $from->id)->value('quantity'));
        $this->assertSame(4, PartStock::where('part_id', $part->id)->where('location_id', $to->id)->value('quantity'));
        $this->assertSame(6, $part->fresh()->quantity);
    }

    public function test_stock_endpoint_requires_permission_and_adjusts(): void
    {
        $part = $this->partWithStock(5);
        $this->actingAs($this->userWithRole('Viewer'))->postJson(route('parts.stock', $part), ['operation' => 'add', 'quantity' => 1])->assertForbidden();
        $this->actingAs($this->userWithRole('Parts Manager'))->postJson(route('parts.stock', $part), ['operation' => 'adjust', 'quantity' => 9, 'location_id' => StockLocation::default()->id])->assertOk();
        $this->assertSame(9, $part->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['part_id' => $part->id, 'type' => 'adjust', 'quantity' => 4]);
    }

    public function test_purchase_order_receive_adds_stock_and_payments_track_balance(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['code' => 'S1', 'name' => 'Acme', 'is_active' => true]);
        $part = $this->partWithStock(0);
        $this->actingAs($admin)->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id, 'location_id' => StockLocation::default()->id, 'order_date' => today()->toDateString(), 'shipping_cost' => 10,
            'items' => [['part_id' => $part->id, 'quantity_ordered' => 10, 'unit_cost' => 12.5]],
        ])->assertRedirect();
        $po = PurchaseOrder::first();
        $this->assertEquals(135, $po->total);

        $item = $po->items()->first();
        $this->actingAs($admin)->post(route('purchase-orders.receive', $po), ['receive' => [$item->id => 4]]);
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertSame(4, $part->fresh()->quantity);
        $this->actingAs($admin)->post(route('purchase-orders.receive', $po), ['receive' => [$item->id => 7]])->assertSessionHasErrors('receive');
        $this->actingAs($admin)->post(route('purchase-orders.receive', $po), ['receive' => [$item->id => 6]]);
        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(10, $part->fresh()->quantity);
        $this->assertEquals(12.5, $part->fresh()->purchase_cost);

        $this->actingAs($admin)->post(route('purchase-orders.pay', $po), ['amount' => 200, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('purchase-orders.pay', $po), ['amount' => 135, 'payment_date' => today()->toDateString(), 'method' => 'cash']);
        $this->assertSame('paid', $po->fresh()->payment_status);
    }

    public function test_parts_csv_import(): void
    {
        $csv = "name,sku,purchase_cost,selling_price,quantity,category\nWiper Blade,WB-1,4.50,12.00,7,Wipers\nBad,,abc,1,1,\n";
        $file = UploadedFile::fake()->createWithContent('parts.csv', $csv);
        $this->actingAs($this->admin())->post(route('parts.import.store'), ['file' => $file])->assertSessionHas('import_errors');
        $part = Part::where('sku', 'WB-1')->first();
        $this->assertSame(7, $part->quantity);
        $this->assertSame('Wipers', $part->category->name);
    }
}
