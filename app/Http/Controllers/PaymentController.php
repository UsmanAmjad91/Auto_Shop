<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentReceipt;
use App\Services\InvoicePdf;
use App\Services\PaymentService;
use App\Support\DataTable;
use App\Support\Exporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    protected function table(Request $request): DataTable
    {
        return DataTable::make(Payment::query()->with(['customer', 'allocations.invoice', 'receiver']), $request)
            ->searchable(['payment_number', 'reference', 'notes', 'customer.first_name', 'customer.last_name', 'customer.company_name'])
            ->sortable(['payment_date' => 'payment_date', 'amount' => 'amount', 'payment_number' => 'payment_number'], 'payment_date')
            ->filters(fn ($q, $r) => $q->when($r->method, fn ($x) => $x->where('method', $r->method))
                ->when($r->type, fn ($x) => $x->where('type', $r->type))
                ->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->customer_id, fn ($x) => $x->where('customer_id', $r->customer_id))
                ->when($r->date_from, fn ($x) => $x->whereDate('payment_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('payment_date', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('payments.index');
        }

        return $this->table($request)->transform(fn (Payment $p) => [
            'id' => $p->id, 'url' => route('payments.show', $p), 'payment_number' => $p->payment_number, 'date' => fmt_date($p->payment_date),
            'customer' => $p->customer?->display_name, 'type' => config('autoshop.enums.payment_types.'.$p->type, $p->type),
            'method' => config('autoshop.enums.payment_methods.'.$p->method, $p->method), 'reference' => $p->reference,
            'amount' => $p->signedAmount(), 'invoices' => $p->allocations->map(fn ($a) => $a->invoice?->invoice_number)->implode(', '),
            'status' => ['label' => status_label($p->status), 'color' => status_color($p->status)], 'by' => $p->receiver?->name,
            'actions' => [['label' => 'View', 'icon' => 'eye', 'url' => route('payments.show', $p)],
                ['label' => 'Receipt', 'icon' => 'printer', 'url' => route('payments.receipt', $p), 'target' => '_blank']],
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn ($p) => ['number' => $p->payment_number, 'date' => $p->payment_date->toDateString(),
            'customer' => $p->customer?->display_name, 'type' => $p->type, 'method' => $p->method, 'reference' => $p->reference,
            'amount' => $p->signedAmount(), 'status' => $p->status, 'invoices' => $p->allocations->map(fn ($a) => $a->invoice?->invoice_number)->implode(' ')]);

        return Exporter::download($request->input('format', 'csv'), 'Payments', ['number' => 'Payment #', 'date' => 'Date', 'customer' => 'Customer',
            'type' => 'Type', 'method' => 'Method', 'reference' => 'Reference', 'amount' => 'Amount', 'status' => 'Status', 'invoices' => 'Invoices'], $rows);
    }

    public function create(Request $request)
    {
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;

        return view('payments.form', ['customer' => $customer, 'invoiceId' => $request->invoice_id]);
    }

    public function openInvoices(Customer $customer)
    {
        return response()->json(Invoice::where('customer_id', $customer->id)->open()->with('financing')->orderBy('due_date')->get()
            ->map(fn ($i) => ['id' => $i->id, 'number' => $i->invoice_number, 'date' => fmt_date($i->invoice_date), 'due' => fmt_date($i->due_date),
                'total' => (float) $i->total, 'balance' => (float) $i->balance, 'lender_pending' => $i->pendingFinancedAmount(),
                'customer_due' => $i->customerBalance(), 'overdue' => $i->isOverdue()]));
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $customer = Customer::findOrFail($data['customer_id']);
        $payment = $this->payments->record($customer, $data, $data['allocations']);

        if ($request->boolean('send_receipt') && $customer->email && settings()->bool('notifications.payment_receipt_email', true)) {
            Notification::route('mail', $customer->email)->notify(new PaymentReceipt($payment));
        }

        return redirect()->route('payments.show', $payment)->with('success', "Payment {$payment->payment_number} recorded.");
    }

    public function show(Payment $payment)
    {
        return view('payments.show', ['payment' => $payment->load(['customer', 'allocations.invoice', 'receiver', 'refunds.allocations.invoice', 'refundOf'])]);
    }

    public function receipt(Request $request, Payment $payment)
    {
        $payment->load(['customer', 'allocations.invoice', 'receiver']);
        $data = ['payment' => $payment, 'logo' => InvoicePdf::logoDataUri()];

        return $request->boolean('pdf')
            ? Pdf::loadView('payments.receipt-pdf', $data)->download("receipt-{$payment->payment_number}.pdf")
            : view('payments.receipt', $data + ['pdf' => false]);
    }

    public function refund(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_keys(config('autoshop.enums.payment_methods')))],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $refund = $this->payments->refund($payment, Invoice::findOrFail($data['invoice_id']), (float) $data['amount'], $data['method'], $data['reason']);

        return redirect()->route('payments.show', $refund)->with('success', 'Refund recorded.');
    }

    public function reverse(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->payments->reverse($payment, $data['reason']);

        return back()->with('success', 'Payment reversed. Invoice balances were restored.');
    }
}
