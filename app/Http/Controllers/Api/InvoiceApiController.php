<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceApiController extends Controller
{
    public function index(Request $request)
    {
        return Invoice::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest('invoice_date')->paginate(min(100, max(1, (int) $request->input('per_page', 25))))
            ->through(fn ($i) => $i->only(['id', 'invoice_number', 'type', 'customer_id', 'customer_name', 'vehicle_id', 'invoice_date', 'due_date',
                'status', 'payment_status', 'total', 'amount_paid', 'balance']));
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load(['items:id,invoice_id,item_type,description,quantity,unit_price,line_total,taxable', 'financing'])
            ->only(['id', 'invoice_number', 'type', 'customer_id', 'customer_name', 'vehicle_id', 'invoice_date', 'due_date', 'status',
                'payment_status', 'subtotal', 'discount_amount', 'tax_rate', 'tax_amount', 'total', 'amount_paid', 'balance', 'items', 'financing']);
    }

    public function pay(Request $request, Invoice $invoice, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(['cash', 'credit_card', 'debit_card', 'bank_transfer', 'check', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', 'in:payment,deposit'],
        ]);
        $payment = $payments->record($invoice->customer, $data + ['type' => $data['type'] ?? 'payment'], [$invoice->id => $data['amount']]);

        return response()->json(['payment_number' => $payment->payment_number, 'invoice_balance' => $invoice->fresh()->balance], 201);
    }
}
