<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceJob;
use App\Models\Vehicle;
use App\Notifications\InvoiceIssued;
use App\Notifications\PaymentReminder;
use App\Services\AuditLogger;
use App\Services\InvoicePdf;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\DataTable;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoices) {}

    protected function table(Request $request): DataTable
    {
        return DataTable::make(Invoice::query()->with(['vehicle', 'salesRep']), $request)
            ->searchable(['invoice_number', 'customer_name', 'customer_email', 'customer_phone', 'vehicle.vin', 'vehicle.stock_number'])
            ->sortable(['invoice_number' => 'invoice_number', 'invoice_date' => 'invoice_date', 'due_date' => 'due_date',
                'total' => 'total', 'balance' => 'balance', 'customer' => 'customer_name'], 'invoice_date')
            ->filters(fn ($q, $r) => $q->when($r->type, fn ($x) => $x->where('type', $r->type))
                ->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->payment_status, fn ($x) => $x->where('payment_status', $r->payment_status))
                ->when($r->customer_id, fn ($x) => $x->where('customer_id', $r->customer_id))
                ->when($r->vehicle_id, fn ($x) => $x->where('vehicle_id', $r->vehicle_id))
                ->when($r->sales_rep_id, fn ($x) => $x->where('sales_rep_id', $r->sales_rep_id))
                ->when(is_numeric($r->min_total), fn ($x) => $x->where('total', '>=', (float) $r->min_total))
                ->when(is_numeric($r->max_total), fn ($x) => $x->where('total', '<=', (float) $r->max_total))
                ->when($r->date_from, fn ($x) => $x->whereDate('invoice_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('invoice_date', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('invoices.index');
        }
        $user = $request->user();

        return $this->table($request)->transform(fn (Invoice $i) => [
            'id' => $i->id, 'url' => route('invoices.show', $i), 'invoice_number' => $i->invoice_number,
            'type' => config('autoshop.enums.invoice_types.'.$i->type), 'customer' => $i->customer_name,
            'vehicle' => $i->vehicle ? $i->vehicle->title().' ('.$i->vehicle->vin.')' : null,
            'invoice_date' => fmt_date($i->invoice_date), 'due_date' => fmt_date($i->due_date),
            'total' => (float) $i->total, 'paid' => (float) $i->amount_paid, 'balance' => (float) $i->balance,
            'status' => ['label' => status_label($i->status), 'color' => status_color($i->status)],
            'payment_status' => ['label' => status_label($i->payment_status), 'color' => status_color($i->payment_status)],
            'rep' => $i->salesRep?->name,
            'actions' => array_values(array_filter([
                ['label' => 'View', 'icon' => 'eye', 'url' => route('invoices.show', $i)],
                $user->can('invoices.edit') && ! $i->isVoid() && (float) $i->amount_paid == 0 ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('invoices.edit', $i)] : null,
                ['label' => 'PDF', 'icon' => 'filetype-pdf', 'url' => route('invoices.pdf', $i), 'target' => '_blank'],
                $user->can('payments.create') && ! in_array($i->status, ['draft', 'void', 'paid'], true) ? ['label' => 'Record payment', 'icon' => 'cash-coin', 'url' => route('payments.create', ['customer_id' => $i->customer_id, 'invoice_id' => $i->id])] : null,
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn ($i) => ['number' => $i->invoice_number, 'date' => $i->invoice_date->toDateString(),
            'due' => $i->due_date?->toDateString(), 'type' => $i->type, 'customer' => $i->customer_name, 'vehicle' => $i->vehicle?->vin,
            'subtotal' => $i->subtotal, 'discount' => $i->discount_amount, 'tax' => $i->tax_amount, 'total' => $i->total,
            'paid' => $i->amount_paid, 'balance' => $i->balance, 'status' => $i->status, 'payment_status' => $i->payment_status]);

        return Exporter::download($request->input('format', 'csv'), 'Invoices', ['number' => 'Invoice #', 'date' => 'Date', 'due' => 'Due', 'type' => 'Type',
            'customer' => 'Customer', 'vehicle' => 'VIN', 'subtotal' => 'Subtotal', 'discount' => 'Discount', 'tax' => 'Tax', 'total' => 'Total',
            'paid' => 'Paid', 'balance' => 'Balance', 'status' => 'Status', 'payment_status' => 'Payment Status'], $rows);
    }

    public function create(Request $request)
    {
        $invoice = new Invoice([
            'type' => $request->input('type', 'repair'), 'invoice_date' => today(),
            'due_date' => today()->addDays((int) settings('general.invoice_due_days', 15)),
            'tax_rate' => settings('general.tax_rate', 0), 'discount_type' => 'fixed', 'discount_value' => 0,
            'terms' => settings('general.invoice_terms'), 'customer_id' => $request->customer_id, 'vehicle_id' => $request->vehicle_id,
        ]);
        $items = [];

        if ($request->service_job_id && ($job = ServiceJob::with('items', 'vehicle', 'customer')->find($request->service_job_id))) {
            $this->authorize('view', $job);
            $invoice->type = InvoiceService::typeForJob($job);
            $invoice->customer_id = $job->customer_id ?? $job->vehicle?->customer_id;
            $invoice->vehicle_id = $job->vehicle_id;
            $items = $this->invoices->itemsFromJob($job);
            $items[0]['label'] = $job->job_number.' — '.$job->title;
        }
        if ($request->vehicle_id && $request->type === 'vehicle_sale' && ($v = Vehicle::find($request->vehicle_id))) {
            $items = [['item_type' => 'vehicle', 'vehicle_id' => $v->id, 'label' => $v->title().' ('.$v->stock_number.')', 'quantity' => 1,
                'unit_price' => (float) $v->asking_price, 'description' => '', 'taxable' => true]];
        }

        return view('invoices.form', $this->formData($invoice, $items));
    }

    public function store(InvoiceRequest $request)
    {
        [$data, $items] = $request->invoiceData();
        $issue = $request->input('action') === 'issue' && $request->user()->can('invoices.approve');
        $invoice = $this->invoices->create($data, $items, $issue);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_number} ".($issue ? 'issued.' : 'saved as draft.'));
    }

    public function show(Invoice $invoice)
    {
        return view('invoices.show', ['invoice' => $invoice->load(['items.part', 'items.vehicle', 'items.serviceJob', 'customer', 'vehicle', 'salesRep',
            'allocations.payment.receiver', 'financing.settlementPayment', 'serviceJobs'])]);
    }

    public function edit(Invoice $invoice)
    {
        abort_if($invoice->isVoid(), 403, 'Void invoices cannot be edited.');
        $items = $invoice->items->map(fn ($i) => [
            'item_type' => $i->item_type, 'vehicle_id' => $i->vehicle_id, 'part_id' => $i->part_id, 'service_job_id' => $i->service_job_id,
            'label' => $i->part?->name ?? $i->vehicle?->title() ?? $i->serviceJob?->job_number, 'description' => $i->description,
            'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'taxable' => $i->taxable,
        ])->all();

        return view('invoices.form', $this->formData($invoice, $items));
    }

    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        [$data, $items] = $request->invoiceData();
        $this->invoices->update($invoice, $data, $items);
        if ($request->input('action') === 'issue' && $invoice->fresh()->isDraft() && $request->user()->can('invoices.approve')) {
            $this->invoices->issue($invoice->fresh());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 422, 'Only draft invoices can be deleted. Void issued invoices instead.');
        $invoice->items()->delete();
        $invoice->delete();

        return $this->done($request, 'Draft invoice deleted.', route('invoices.index'));
    }

    public function issue(Request $request, Invoice $invoice)
    {
        $allowNegative = $request->user()->can('parts.negative_stock') && $request->boolean('allow_negative');
        $this->invoices->issue($invoice, $allowNegative);

        return back()->with('success', 'Invoice issued. Stock and vehicle status were updated.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->invoices->void($invoice, $request->reason);

        return back()->with('success', 'Invoice voided. Stock and vehicle changes were reversed.');
    }

    public function pdf(Request $request, Invoice $invoice, InvoicePdf $pdf)
    {
        $doc = $pdf->make($invoice);

        return $request->boolean('download') ? $doc->download("{$invoice->invoice_number}.pdf") : $doc->stream("{$invoice->invoice_number}.pdf");
    }

    public function print(Invoice $invoice)
    {
        return view('invoices.print', ['invoice' => $invoice->load(['items', 'customer', 'vehicle', 'financing', 'allocations.payment']),
            'logo' => InvoicePdf::logoDataUri()]);
    }

    public function email(Request $request, Invoice $invoice)
    {
        abort_if($invoice->isDraft() || $invoice->isVoid(), 422, 'Issue the invoice before emailing it.');
        abort_unless(settings()->bool('notifications.invoice_email', true), 403, 'Invoice emails are disabled in settings.');
        $data = $request->validate(['to' => ['required', 'email'], 'message' => ['nullable', 'string', 'max:2000']]);
        Notification::route('mail', $data['to'])->notify(new InvoiceIssued($invoice, $data['message'] ?? null));
        AuditLogger::log('emailed', $invoice, "Invoice {$invoice->invoice_number} emailed to {$data['to']}");

        return back()->with('success', 'Invoice queued for email to '.$data['to'].'.');
    }

    public function remind(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->customer_email && (float) $invoice->balance > 0, 422, 'No email address or no balance due.');
        Notification::route('mail', $invoice->customer_email)->notify(new PaymentReminder($invoice));
        $invoice->forceFill(['last_reminder_at' => now()])->saveQuietly();
        AuditLogger::log('reminder_sent', $invoice, "Payment reminder sent for {$invoice->invoice_number}");

        return back()->with('success', 'Payment reminder queued.');
    }

    public function financing(Request $request, Invoice $invoice, PaymentService $payments)
    {
        $data = $request->validate([
            'lender_name' => ['required', 'string', 'max:255'], 'lender_contact' => ['nullable', 'string', 'max:255'],
            'loan_reference' => ['nullable', 'string', 'max:100'], 'financed_amount' => ['required', 'numeric', 'min:0.01'],
            'down_payment' => ['nullable', 'numeric', 'min:0'], 'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:120'], 'status' => ['required', Rule::in(['pending', 'approved', 'cancelled'])],
            'approved_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $payments->saveFinancing($invoice, $data);

        return back()->with('success', 'Financing saved. The financed amount stays a lender receivable until settlement is recorded.');
    }

    public function settleFinancing(Request $request, Invoice $invoice, PaymentService $payments)
    {
        $data = $request->validate(['settled_date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100']]);
        abort_unless($invoice->financing, 404);
        $payments->settleFinancing($invoice->financing, $data['settled_date'], $data['reference'] ?? null);

        return back()->with('success', 'Lender settlement recorded as cash received.');
    }

    protected function formData(Invoice $invoice, array $items): array
    {
        $customer = $invoice->customer_id ? Customer::find($invoice->customer_id) : null;
        $vehicle = $invoice->vehicle_id ? Vehicle::find($invoice->vehicle_id) : null;

        return compact('invoice', 'items', 'customer', 'vehicle');
    }

    /** Bulk: queue payment reminders for selected open invoices. */
    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer'], 'action' => ['required', 'in:remind']]);
        abort_unless($request->user()->can('invoices.email'), 403);
        $n = 0;
        foreach (Invoice::whereIn('id', $data['ids'])->open()->whereNotNull('customer_email')->get() as $invoice) {
            Notification::route('mail', $invoice->customer_email)->notify(new PaymentReminder($invoice));
            $invoice->forceFill(['last_reminder_at' => now()])->saveQuietly();
            AuditLogger::log('reminder_sent', $invoice, "Payment reminder sent for {$invoice->invoice_number}");
            $n++;
        }

        return $this->done($request, "{$n} reminder(s) queued (only open invoices with an email address).");
    }
}
