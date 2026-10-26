<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ServiceJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function issueVehicleSale($vehicle, $customer, float $price = 20000): Invoice
    {
        return app(InvoiceService::class)->create(['type' => 'vehicle_sale', 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'tax_rate' => 0],
            [['item_type' => 'vehicle', 'vehicle_id' => $vehicle->id, 'unit_price' => $price]], true);
    }

    public function test_parts_invoice_totals_tax_discount_and_stock_deduction_on_issue(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $part = $this->partWithStock(10, ['purchase_cost' => 20, 'selling_price' => 50]);

        $this->actingAs($admin)->post(route('invoices.store'), [
            'type' => 'parts_sale', 'customer_id' => $customer->id, 'invoice_date' => today()->toDateString(), 'discount_type' => 'percent',
            'discount_value' => 10, 'tax_rate' => 8, 'action' => 'draft',
            'items' => [
                ['item_type' => 'part', 'part_id' => $part->id, 'quantity' => 3, 'unit_price' => 50, 'taxable' => 1],
                ['item_type' => 'labor', 'description' => 'Fitting', 'quantity' => 1, 'unit_price' => 50, 'taxable' => 0],
            ],
        ])->assertRedirect();

        $invoice = Invoice::first();
        // subtotal 200, discount 20, taxable 150 - 15 = 135 * 8% = 10.80, total 190.80
        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(20, $invoice->discount_amount);
        $this->assertEquals(10.80, $invoice->tax_amount);
        $this->assertEquals(190.80, $invoice->total);
        $this->assertEquals(60, $invoice->cost_total);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(10, $part->fresh()->quantity); // drafts never touch stock

        $this->actingAs($admin)->post(route('invoices.issue', $invoice))->assertRedirect();
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame(7, $part->fresh()->quantity);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);

        $this->actingAs($admin)->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_vehicle_sale_marks_sold_and_void_reverses(): void
    {
        $this->actingAs($this->admin());
        $vehicle = $this->vehicle(['purchase_price' => 15000]);
        $customer = $this->customer();
        $invoice = $this->issueVehicleSale($vehicle, $customer, 20000);

        $vehicle->refresh();
        $this->assertSame('sold', $vehicle->inventory_status);
        $this->assertEquals(20000, $vehicle->sale_price);
        $this->assertEquals(5000, $vehicle->profit());
        $this->assertSame($customer->id, $vehicle->sold_to_customer_id);

        // Same vehicle cannot be sold twice
        try {
            $this->issueVehicleSale($vehicle, $this->customer(), 21000);
            $this->fail('Vehicle sold twice');
        } catch (ValidationException) {
        }

        app(InvoiceService::class)->void($invoice->fresh(), 'Customer backed out');
        $vehicle->refresh();
        $this->assertSame('available', $vehicle->inventory_status);
        $this->assertNull($vehicle->sale_price);
        $this->assertSame('void', $invoice->fresh()->status);
    }

    public function test_customer_vehicle_cannot_be_sold(): void
    {
        $this->actingAs($this->admin());
        $this->expectException(ValidationException::class);
        $this->issueVehicleSale($this->vehicle(['ownership_type' => 'customer']), $this->customer());
    }

    public function test_partial_full_payments_refund_and_reversal(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $invoice = app(InvoiceService::class)->create(['type' => 'repair', 'customer_id' => $customer->id, 'tax_rate' => 0],
            [['item_type' => 'labor', 'description' => 'Work', 'quantity' => 1, 'unit_price' => 1000]], true);

        // over-allocation rejected
        $this->actingAs($admin)->post(route('payments.store'), ['customer_id' => $customer->id, 'type' => 'payment', 'payment_date' => today()->toDateString(),
            'amount' => 1500, 'method' => 'cash', 'allocations' => [$invoice->id => 1500]])->assertSessionHasErrors('allocations');

        $this->actingAs($admin)->post(route('payments.store'), ['customer_id' => $customer->id, 'type' => 'deposit', 'payment_date' => today()->toDateString(),
            'amount' => 400, 'method' => 'cash', 'allocations' => [$invoice->id => 400]])->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(400, $invoice->amount_paid);
        $this->assertEquals(600, $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status);

        $second = app(PaymentService::class)->record($customer, ['amount' => 600, 'method' => 'bank_transfer', 'payment_date' => today()->toDateString()], [$invoice->id => 600]);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->balance);

        // refund 100 of the second payment
        $this->actingAs($admin)->post(route('payments.refund', $second), ['invoice_id' => $invoice->id, 'amount' => 100, 'method' => 'bank_transfer', 'reason' => 'Goodwill']);
        $this->assertEquals(900, $invoice->fresh()->amount_paid);
        $this->assertEquals(100, $invoice->fresh()->balance);
        // cannot refund more than allocated
        $this->actingAs($admin)->post(route('payments.refund', $second), ['invoice_id' => $invoice->id, 'amount' => 600, 'method' => 'cash', 'reason' => 'x'])->assertSessionHasErrors('amount');

        // reversal of a payment with a refund requires reversing the refund first
        $this->actingAs($admin)->post(route('payments.reverse', $second), ['reason' => 'Bounced'])->assertSessionHasErrors('payment');
        $refund = Payment::where('type', 'refund')->first();
        $this->actingAs($admin)->post(route('payments.reverse', $refund), ['reason' => 'Entered in error']);
        $this->actingAs($admin)->post(route('payments.reverse', $second), ['reason' => 'Bounced']);
        $invoice->refresh();
        $this->assertEquals(400, $invoice->amount_paid);
        $this->assertEquals(600, $invoice->balance);
        $this->assertSame('reversed', $second->fresh()->status);

        // invoices with payments cannot be voided
        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->void($invoice, 'test');
    }

    public function test_full_card_numbers_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $invoice = app(InvoiceService::class)->create(['type' => 'repair', 'customer_id' => $customer->id],
            [['item_type' => 'labor', 'description' => 'W', 'quantity' => 1, 'unit_price' => 100]], true);
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->record($customer, ['amount' => 100, 'method' => 'credit_card', 'reference' => '4111 1111 1111 1111',
            'payment_date' => today()->toDateString()], [$invoice->id => 100]);
    }

    public function test_financing_is_receivable_until_settled(): void
    {
        $this->actingAs($this->admin());
        $vehicle = $this->vehicle(['purchase_price' => 18000]);
        $customer = $this->customer();
        $invoice = $this->issueVehicleSale($vehicle, $customer, 25000);
        $payments = app(PaymentService::class);

        $payments->record($customer, ['amount' => 5000, 'method' => 'cash', 'type' => 'deposit', 'payment_date' => today()->toDateString()], [$invoice->id => 5000]);
        $payments->saveFinancing($invoice->fresh(), ['lender_name' => 'Bank', 'financed_amount' => 20000, 'down_payment' => 5000, 'status' => 'approved']);

        $invoice->refresh();
        $this->assertEquals(5000, $invoice->amount_paid);       // financed amount is NOT cash
        $this->assertEquals(20000, $invoice->balance);
        $this->assertEquals(0, $invoice->customerBalance());    // customer owes nothing more
        $this->assertSame('pending', $invoice->payment_status);
        $this->assertEquals(5000, app(DashboardService::class)->cashCollectedBetween(today()->subDay(), today()));

        $payments->settleFinancing($invoice->financing, today()->toDateString(), 'FUND-1');
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(25000, $invoice->amount_paid);
        $this->assertSame('paid', $vehicle->fresh()->payment_status);
        $this->assertEquals(25000, app(DashboardService::class)->cashCollectedBetween(today()->subDay(), today()));
    }

    public function test_job_invoice_links_job_and_does_not_double_deduct_parts(): void
    {
        $this->actingAs($this->admin());
        $vehicle = $this->vehicle(['ownership_type' => 'customer']);
        $customer = $this->customer();
        $part = $this->partWithStock(5);
        $jobs = app(ServiceJobService::class);
        $job = $jobs->create(['category' => 'repair', 'vehicle_id' => $vehicle->id, 'customer_id' => $customer->id, 'title' => 'Fix', 'priority' => 'normal']);
        $jobs->addItem($job, ['item_type' => 'part', 'part_id' => $part->id, 'quantity' => 2]);
        $this->assertSame(3, $part->fresh()->quantity);

        $inv = app(InvoiceService::class);
        $invoice = $inv->create(['type' => 'repair', 'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id], $inv->itemsFromJob($job->fresh('items')), true);
        $this->assertSame(3, $part->fresh()->quantity); // still 3: parts were issued to the job already
        $this->assertSame($invoice->id, $job->fresh()->invoice_id);
        $this->assertEquals($job->fresh()->customer_charge, $invoice->subtotal);

        // Job cannot be billed twice
        $this->expectException(ValidationException::class);
        $inv->create(['type' => 'repair', 'customer_id' => $customer->id], $inv->itemsFromJob($job->fresh('items')));
    }

    public function test_overdue_status_refresh(): void
    {
        $this->actingAs($this->admin());
        $customer = $this->customer();
        $invoice = app(InvoiceService::class)->create(['type' => 'repair', 'customer_id' => $customer->id, 'invoice_date' => today()->subDays(40)->toDateString(),
            'due_date' => today()->subDays(10)->toDateString()], [['item_type' => 'labor', 'description' => 'W', 'quantity' => 1, 'unit_price' => 100]], true);
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->artisan('autoshop:refresh-statuses')->assertSuccessful();
    }
}
