@extends('layouts.app')
@section('title', $invoice->invoice_number)
@section('content')
@php $u = auth()->user(); $e = config('autoshop.enums'); $fin = $invoice->financing; $payable = ! in_array($invoice->status, ['draft', 'void', 'paid', 'refunded']); @endphp
<x-page-header :title="'Invoice '.$invoice->invoice_number" :breadcrumbs="['Invoices' => route('invoices.index'), $invoice->invoice_number => null]">
    @if($invoice->isDraft() && $u->can('invoices.approve'))<form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send-check"></i> Issue invoice</button></form>@endif
    @if($payable && $u->can('payments.create'))<a href="{{ route('payments.create', ['customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-cash-coin"></i> Record payment</a>@endif
    @if(! $invoice->isVoid() && $u->can('invoices.edit') && (float) $invoice->amount_paid == 0)<a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endif
    <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('invoices.pdf', $invoice) }}" target="_blank"><i class="bi bi-filetype-pdf me-2"></i>View PDF</a></li>
            <li><a class="dropdown-item" href="{{ route('invoices.pdf', [$invoice, 'download' => 1]) }}"><i class="bi bi-download me-2"></i>Download PDF</a></li>
            @can('invoices.print')<li><a class="dropdown-item" href="{{ route('invoices.print', $invoice) }}" target="_blank"><i class="bi bi-printer me-2"></i>Print</a></li>@endcan
            @if(! $invoice->isDraft() && ! $invoice->isVoid() && $u->can('invoices.email'))
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="bi bi-envelope me-2"></i>Email to customer</a></li>
                @if($invoice->balance > 0 && $invoice->customer_email)<li><a class="dropdown-item" href="{{ route('invoices.remind', $invoice) }}" data-method="POST" data-confirm="Send a payment reminder?"><i class="bi bi-bell me-2"></i>Send payment reminder</a></li>@endif
            @endif
            @can('invoices.view')<li><a class="dropdown-item" href="{{ route('customers.statement', $invoice->customer_id) }}"><i class="bi bi-file-text me-2"></i>Customer statement</a></li>@endcan
            @if(! $invoice->isVoid() && $u->can('invoices.void'))<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#voidModal"><i class="bi bi-x-octagon me-2"></i>Void invoice</a></li>@endif
            @if($invoice->isDraft() && $u->can('invoices.delete'))<li><a class="dropdown-item text-danger" href="{{ route('invoices.destroy', $invoice) }}" data-method="DELETE" data-confirm="Delete this draft?"><i class="bi bi-trash me-2"></i>Delete draft</a></li>@endif
        </ul></div>
</x-page-header>

@if($invoice->isVoid())<div class="alert alert-danger"><strong>VOID</strong> — {{ $invoice->void_reason }} ({{ fmt_date($invoice->voided_at, true) }})</div>@endif
@if($invoice->isOverdue())<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Overdue since {{ fmt_date($invoice->due_date) }}.</div>@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body">
            <div class="row">
                <div class="col-md-6"><div class="small text-secondary">Bill to</div>
                    <div class="fw-semibold"><a href="{{ route('customers.show', $invoice->customer_id) }}">{{ $invoice->customer_name }}</a></div>
                    <div class="small">{{ $invoice->customer_address }}</div><div class="small">{{ $invoice->customer_email }} {{ $invoice->customer_phone }}</div></div>
                <div class="col-md-6"><dl class="dl-grid">
                    <dt>Type</dt><dd>{{ $e['invoice_types'][$invoice->type] }}</dd>
                    <dt>Date / due</dt><dd>{{ fmt_date($invoice->invoice_date) }} / {{ fmt_date($invoice->due_date) ?: '—' }}</dd>
                    <dt>Status</dt><dd><x-status :status="$invoice->status" /> <x-status :status="$invoice->payment_status" /></dd>
                    @if($invoice->vehicle)<dt>Vehicle</dt><dd><a href="{{ route('vehicles.show', $invoice->vehicle) }}">{{ $invoice->vehicle->title() }}</a><br><span class="font-monospace small">{{ $invoice->vehicle->vin }}</span></dd>@endif
                    <dt>Sales rep</dt><dd>{{ $invoice->salesRep?->name ?? '—' }}</dd>
                </dl></div>
            </div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Item</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Total</th></tr></thead>
            <tbody>@foreach($invoice->items as $i)<tr>
                <td><span class="badge text-bg-light border">{{ $e['invoice_item_types'][$i->item_type] }}</span></td>
                <td>{{ $i->description }} @if($i->serviceJob)<a href="{{ route('service-jobs.show', $i->service_job_id) }}" class="small">view job</a>@endif
                    @if($i->item_type === 'part')<span class="small text-secondary">{{ $i->stock_deducted ? '· stock deducted' : '· stock not yet deducted' }}</span>@endif
                    @unless($i->taxable)<span class="small text-secondary">· non-taxable</span>@endunless</td>
                <td class="text-end">{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }}</td><td class="text-end money">{{ money($i->unit_price) }}</td><td class="text-end money">{{ money($i->line_total) }}</td></tr>@endforeach</tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end money">{{ money($invoice->subtotal) }}</td></tr>
                @if($invoice->discount_amount > 0)<tr><td colspan="4" class="text-end">Discount {{ $invoice->discount_type === 'percent' ? '('.(float) $invoice->discount_value.'%)' : '' }}</td><td class="text-end money">-{{ money($invoice->discount_amount) }}</td></tr>@endif
                <tr><td colspan="4" class="text-end">{{ settings('general.tax_name', 'Tax') }} ({{ (float) $invoice->tax_rate }}%)</td><td class="text-end money">{{ money($invoice->tax_amount) }}</td></tr>
                <tr class="fw-bold fs-5"><td colspan="4" class="text-end">Total</td><td class="text-end money">{{ money($invoice->total) }}</td></tr>
                <tr><td colspan="4" class="text-end">Amount paid</td><td class="text-end money">{{ money($invoice->amount_paid) }}</td></tr>
                <tr class="fw-bold"><td colspan="4" class="text-end">Balance due</td><td class="text-end money {{ $invoice->balance > 0 ? 'text-danger' : '' }}">{{ money($invoice->balance) }}</td></tr>
            </tfoot>
        </table></div></div>
        @if($invoice->notes || $invoice->terms)<div class="card card-body small mb-3">@if($invoice->notes)<p><strong>Notes:</strong> {{ $invoice->notes }}</p>@endif @if($invoice->terms)<p class="mb-0"><strong>Terms:</strong> {{ $invoice->terms }}</p>@endif</div>@endif
        @if($u->can('audit_logs.view'))
            <div class="card"><div class="card-header">Audit trail</div><ul class="list-group list-group-flush small">
                @foreach($invoice->auditLogs()->with('user')->limit(20)->get() as $l)<li class="list-group-item d-flex justify-content-between"><span>{{ $l->description ?? $l->event }} — {{ $l->user?->name ?? 'System' }}</span><span class="text-secondary">{{ $l->created_at->format('Y-m-d H:i') }}</span></li>@endforeach
            </ul></div>
        @endif
    </div>
    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-header">Payments</div>
            <ul class="list-group list-group-flush small">
                @forelse($invoice->allocations as $a)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->payment_number }}</a> · {{ fmt_date($a->payment->payment_date) }}<br>
                            <span class="text-secondary">{{ $e['payment_types'][$a->payment->type] ?? $a->payment->type }} · {{ $e['payment_methods'][$a->payment->method] ?? '' }}</span>
                            @if($a->payment->status === 'reversed')<span class="badge text-bg-danger">Reversed</span>@endif</span>
                        <span class="money {{ $a->payment->isRefund() ? 'text-danger' : '' }} {{ $a->payment->status === 'reversed' ? 'text-decoration-line-through' : '' }}">{{ $a->payment->isRefund() ? '-' : '' }}{{ money($a->amount) }}</span></li>
                @empty <li class="list-group-item text-secondary">No payments yet.</li> @endforelse
            </ul></div>

        @if($invoice->type === 'vehicle_sale' || $fin)
        <div class="card mb-3"><div class="card-header">Financing / loan</div><div class="card-body small">
            @if($fin && $fin->status !== 'cancelled')
                <dl class="dl-grid">
                    <dt>Lender</dt><dd>{{ $fin->lender_name }} {{ $fin->lender_contact }}</dd><dt>Reference</dt><dd>{{ $fin->loan_reference ?: '—' }}</dd>
                    <dt>Financed</dt><dd class="fw-semibold">{{ money($fin->financed_amount) }}</dd><dt>Down payment</dt><dd>{{ money($fin->down_payment) }}</dd>
                    <dt>Terms</dt><dd>{{ $fin->term_months ? $fin->term_months.' mo' : '—' }} {{ $fin->interest_rate ? '@ '.$fin->interest_rate.'%' : '' }}</dd>
                    <dt>Status</dt><dd><x-status :status="$fin->status" :label="\App\Models\FinancingRecord::STATUSES[$fin->status]" /></dd>
                    @if($fin->settled_date)<dt>Settled</dt><dd>{{ fmt_date($fin->settled_date) }} ({{ $fin->settlementPayment?->payment_number }})</dd>@endif
                </dl>
                <div class="alert alert-light border small mb-2">Customer owes <strong>{{ money($invoice->customerBalance()) }}</strong>; lender owes <strong>{{ money($invoice->pendingFinancedAmount()) }}</strong>. Lender funds count as cash only after settlement.</div>
                @if(in_array($fin->status, ['pending', 'approved']) && $u->can('payments.manage_payments'))
                    <form method="POST" action="{{ route('invoices.financing.settle', $invoice) }}" class="row g-2">@csrf
                        <div class="col-6"><input type="date" name="settled_date" value="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                        <div class="col-6"><input name="reference" class="form-control form-control-sm" placeholder="Funding ref"></div>
                        <div class="col-12"><button class="btn btn-sm btn-success w-100" onclick="return confirm('Record lender settlement of {{ money($fin->financed_amount) }}?')">Record lender settlement</button></div></form>
                @endif
            @elseif(! $invoice->isDraft() && ! $invoice->isVoid() && $invoice->balance > 0)
                <p class="text-secondary">No financing recorded.</p>
            @endif
            @if(! $invoice->isDraft() && ! $invoice->isVoid() && $u->can('payments.manage_payments') && (! $fin || in_array($fin->status, ['pending', 'approved', 'cancelled'])) && $invoice->balance > 0)
                <button class="btn btn-sm btn-outline-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#financeModal">{{ $fin && $fin->status !== 'cancelled' ? 'Edit financing' : 'Add financing' }}</button>
            @endif
        </div></div>
        @endif
        <div class="card"><div class="card-body small text-secondary">
            Created {{ fmt_date($invoice->created_at, true) }}@if($invoice->issued_at) · issued {{ fmt_date($invoice->issued_at, true) }}@endif
            @if($invoice->last_reminder_at)<br>Last reminder {{ $invoice->last_reminder_at->diffForHumans() }}@endif
        </div></div>
    </div>
</div>

@push('modals')
<x-modal id="voidModal" title="Void invoice">
    <form method="POST" action="{{ route('invoices.void', $invoice) }}">@csrf
        <p class="small">Voiding reverses stock deductions and returns any sold vehicle to inventory. Invoices with payments must be refunded/reversed first.</p>
        <x-form.input name="reason" label="Reason" required />
        <div class="text-end mt-3"><button class="btn btn-danger">Void invoice</button></div>
    </form>
</x-modal>
<x-modal id="emailModal" title="Email invoice">
    <form method="POST" action="{{ route('invoices.email', $invoice) }}">@csrf
        <x-form.input name="to" type="email" label="To" :value="$invoice->customer_email" required />
        <div class="mt-2"><x-form.textarea name="message" label="Message (optional)" rows="3" /></div>
        <div class="form-text">The PDF invoice is attached. Requires a working mail configuration and queue worker.</div>
        <div class="text-end mt-3"><button class="btn btn-primary">Send</button></div>
    </form>
</x-modal>
<x-modal id="financeModal" title="Vehicle financing" size="lg">
    <form method="POST" action="{{ route('invoices.financing', $invoice) }}">@csrf
        <div class="row g-2">
            <x-form.input name="lender_name" label="Lender" :value="$fin?->status !== 'cancelled' ? $fin?->lender_name : null" required col="col-md-6" />
            <x-form.input name="lender_contact" label="Lender contact" :value="$fin?->lender_contact" col="col-md-6" />
            <x-form.input name="financed_amount" type="number" step="0.01" min="0.01" :max="$invoice->balance" label="Financed amount" :value="$fin?->status !== 'cancelled' ? $fin?->financed_amount : $invoice->balance" required col="col-md-4" />
            <x-form.input name="down_payment" type="number" step="0.01" min="0" label="Down payment (record separately as a deposit)" :value="$fin?->down_payment ?? $invoice->amount_paid" col="col-md-4" />
            <x-form.input name="loan_reference" label="Loan / application #" :value="$fin?->loan_reference" col="col-md-4" />
            <x-form.input name="interest_rate" type="number" step="0.001" min="0" label="APR %" :value="$fin?->interest_rate" col="col-md-4" />
            <x-form.input name="term_months" type="number" min="1" label="Term (months)" :value="$fin?->term_months" col="col-md-4" />
            <x-form.select name="status" label="Status" :options="['pending' => 'Pending', 'approved' => 'Approved', 'cancelled' => 'Cancelled']" :value="$fin?->status ?? 'pending'" col="col-md-4" />
            <x-form.input name="approved_date" type="date" label="Approved date" :value="$fin?->approved_date" col="col-md-4" />
            <x-form.textarea name="notes" label="Notes" :value="$fin?->notes" rows="2" col="col-md-8" />
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary">Save financing</button></div>
    </form>
</x-modal>
@endpush
@endsection
