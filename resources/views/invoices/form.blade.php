@extends('layouts.app')
@section('title', $invoice->exists ? 'Edit '.$invoice->invoice_number : 'New invoice')
@section('content')
@php $e = config('autoshop.enums'); @endphp
<x-page-header :title="$invoice->exists ? 'Edit invoice '.$invoice->invoice_number : 'New invoice'" :breadcrumbs="['Invoices' => route('invoices.index'), ($invoice->exists ? $invoice->invoice_number : 'New') => null]" />
@if($invoice->exists && ! $invoice->isDraft())<div class="alert alert-warning small">This invoice is already issued. Saving will reverse and re-apply its stock and vehicle effects. Changes are recorded in the audit log.</div>@endif
<form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" id="invoice-form"
      data-items='@json(old('items', $items))' data-default-item="{{ $invoice->type === 'parts_sale' ? 'part' : 'labor' }}">
    @csrf @if($invoice->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        <x-form.select name="type" label="Invoice type" :options="$e['invoice_types']" :value="$invoice->type" required col="col-md-3" />
        <x-form.remote name="customer_id" type="customers" label="Customer" :value="$invoice->customer_id" :text="$customer?->display_name" required col="col-md-5" create="#quickCustomerModal" id="customer_id" />
        <x-form.remote name="vehicle_id" type="vehicles" label="Vehicle (optional)" :value="$invoice->vehicle_id" :text="$vehicle ? $vehicle->title().' ('.$vehicle->stock_number.')' : null" col="col-md-4" />
        <x-form.input name="invoice_date" type="date" label="Invoice date" :value="$invoice->invoice_date" required col="col-md-3" />
        <x-form.input name="due_date" type="date" label="Due date" :value="$invoice->due_date" col="col-md-3" />
        <x-form.remote name="sales_rep_id" type="users" label="Sales representative" :value="$invoice->sales_rep_id ?? auth()->id()" :text="($invoice->salesRep ?? auth()->user())->name" col="col-md-6" />
    </div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">Line items
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#inv-item-tpl"><i class="bi bi-plus"></i> Add line</button></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th style="width:150px">Type</th><th style="min-width:260px">Item</th><th style="min-width:200px">Description</th><th style="width:100px">Qty</th><th style="width:140px">Unit price</th><th style="width:60px">Tax</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody id="invoice-items" data-repeater data-template="#inv-item-tpl"></tbody>
        </table></div>
    </div>
    <template id="inv-item-tpl"><tr data-row>
        <td><select name="items[__i__][item_type]" data-field="item_type" class="form-select form-select-sm">@foreach($e['invoice_item_types'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></td>
        <td>
            <div data-ref="vehicle"><select name="items[__i__][vehicle_id]" data-field="vehicle_id" data-remote="vehicles" data-params='{"for_sale":1}' data-placeholder="Vehicle for sale…" class="form-select form-select-sm"></select></div>
            <div data-ref="part"><select name="items[__i__][part_id]" data-field="part_id" data-remote="parts" data-placeholder="Search parts…" class="form-select form-select-sm"></select></div>
            <div data-ref="service_job"><select name="items[__i__][service_job_id]" data-field="service_job_id" data-remote="service_jobs" data-params='{"billable":1}' data-depends="#customer_id" data-placeholder="Uninvoiced job…" class="form-select form-select-sm"></select></div>
            <div data-ref="labor"><span class="small text-secondary">Labor charge</span></div><div data-ref="interior"><span class="small text-secondary">Interior repair charge</span></div>
            <div data-ref="detailing"><span class="small text-secondary">Wash / detailing charge</span></div><div data-ref="other"><span class="small text-secondary">Other charge</span></div>
        </td>
        <td><input name="items[__i__][description]" data-field="description" class="form-control form-control-sm" placeholder="Auto for vehicles/parts/jobs"></td>
        <td><input type="number" step="0.01" min="0.01" name="items[__i__][quantity]" data-field="quantity" value="1" class="form-control form-control-sm" required></td>
        <td><input type="number" step="0.01" min="0" name="items[__i__][unit_price]" data-field="unit_price" class="form-control form-control-sm" required></td>
        <td class="text-center"><input type="hidden" name="items[__i__][taxable]" value="0"><input type="checkbox" name="items[__i__][taxable]" data-field="taxable" value="1" class="form-check-input" checked aria-label="Taxable"></td>
        <td class="text-end money line-total"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row aria-label="Remove line"><i class="bi bi-x-lg"></i></button></td>
    </tr></template>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-form.textarea name="notes" label="Notes (shown on invoice)" :value="$invoice->notes" rows="3" />
            <div class="mt-3"><x-form.textarea name="terms" label="Terms and conditions" :value="$invoice->terms" rows="3" /></div>
        </div>
        <div class="col-lg-5"><div class="card card-body">
            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="t-subtotal" class="money"></span></div>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-5 small">Discount</div>
                <div class="col-3"><select name="discount_type" class="form-select form-select-sm"><option value="fixed" @selected(old('discount_type', $invoice->discount_type) === 'fixed')>{{ settings('general.currency_symbol') }}</option><option value="percent" @selected(old('discount_type', $invoice->discount_type) === 'percent')>%</option></select></div>
                <div class="col-4"><input type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', $invoice->discount_value ?? 0) }}" class="form-control form-control-sm" aria-label="Discount"></div>
            </div>
            <div class="d-flex justify-content-between mb-2 text-secondary"><span></span><span id="t-discount" class="money"></span></div>
            <div class="row g-2 mb-2 align-items-center"><div class="col-8 small">{{ settings('general.tax_name', 'Tax') }} rate (%)</div>
                <div class="col-4"><input type="number" step="0.001" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $invoice->tax_rate) }}" class="form-control form-control-sm" aria-label="Tax rate"></div></div>
            <div class="d-flex justify-content-between mb-2"><span>Tax</span><span id="t-tax" class="money"></span></div>
            <div class="d-flex justify-content-between fs-4 fw-bold border-top pt-2"><span>Total</span><span id="t-total" class="money"></span></div>
            <div class="form-text">Final totals are recalculated on the server.</div>
        </div>
        <div class="d-flex gap-2 mt-3 justify-content-end">
            <button name="action" value="draft" class="btn btn-outline-primary">{{ $invoice->exists && ! $invoice->isDraft() ? 'Save changes' : 'Save draft' }}</button>
            @if((! $invoice->exists || $invoice->isDraft()) && auth()->user()->can('invoices.approve'))<button name="action" value="issue" class="btn btn-primary">Save & issue</button>@endif
        </div></div>
    </div>
</form>
@endsection
