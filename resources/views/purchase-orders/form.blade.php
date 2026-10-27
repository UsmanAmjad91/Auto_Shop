@extends('layouts.app')
@section('title', $po->exists ? 'Edit '.$po->po_number : 'New purchase order')
@section('content')
<x-page-header :title="$po->exists ? 'Edit '.$po->po_number : 'New purchase order'" :breadcrumbs="['Purchase orders' => route('purchase-orders.index'), ($po->exists ? $po->po_number : 'New') => null]" />
<form method="POST" action="{{ $po->exists ? route('purchase-orders.update', $po) : route('purchase-orders.store') }}" id="po-form" data-items='@json(old('items', $items))'>
    @csrf @if($po->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        <x-form.remote name="supplier_id" type="suppliers" label="Supplier" :value="$po->supplier_id" :text="$po->supplier?->name" required col="col-md-4"
            :create="auth()->user()->can('suppliers.create') ? '#quickSupplierModal' : null" placeholder="Search suppliers or add a new one…" />
        <x-form.select name="location_id" label="Receive into" :options="$locations" :value="$po->location_id" required col="col-md-2" />
        <x-form.input name="order_date" type="date" label="Order date" :value="$po->order_date" required col="col-md-2" />
        <x-form.input name="expected_date" type="date" label="Expected" :value="$po->expected_date" col="col-md-2" />
        <x-form.input name="due_date" type="date" label="Payment due" :value="$po->due_date" col="col-md-2" />
        <x-form.input name="supplier_invoice_number" label="Supplier invoice #" :value="$po->supplier_invoice_number" col="col-md-4" />
    </div></div>
    <div class="card mb-3"><div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center"><span>Items</span>
        <span class="d-flex gap-2">
            @can('parts.create')<button type="button" class="btn btn-sm btn-outline-secondary" id="po-new-part"><i class="bi bi-box-seam"></i> New part</button>@endcan
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#po-item-tpl"><i class="bi bi-plus"></i> Add item</button>
        </span></div>
        <div class="px-3 pt-2 small text-secondary">Pick an existing part in each row (search by name, part #, OEM or SKU). New part? Type its name and choose <strong>Add…</strong>, or click <strong>New part</strong>.</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="min-width:280px">Part</th><th style="width:120px">Qty</th><th style="width:160px">Unit cost</th><th class="text-end">Line total</th><th></th></tr></thead>
            <tbody id="po-items" data-repeater data-template="#po-item-tpl"></tbody></table></div>
    </div>
    <template id="po-item-tpl"><tr data-row>
        <td><select name="items[__i__][part_id]" data-field="part_id" data-remote="parts" data-placeholder="Search parts…" @can('parts.create') data-create="#quickPartModal" @endcan class="form-select" required></select></td>
        <td><input type="number" name="items[__i__][quantity_ordered]" data-field="quantity_ordered" min="1" class="form-control" required></td>
        <td><input type="number" step="0.01" min="0" name="items[__i__][unit_cost]" data-field="unit_cost" class="form-control" required></td>
        <td class="text-end money line-total"></td><td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td></tr></template>
    <div class="row g-3">
        <div class="col-md-7"><x-form.textarea name="notes" label="Notes" :value="$po->notes" rows="4" /></div>
        <div class="col-md-5"><div class="card card-body">
            <div class="d-flex justify-content-between"><span>Subtotal</span><span id="po-subtotal" class="money"></span></div>
            <div class="row g-2 my-2"><x-form.input name="tax_amount" type="number" step="0.01" min="0" label="Tax" :value="$po->tax_amount ?? 0" col="col-6" /><x-form.input name="shipping_cost" type="number" step="0.01" min="0" label="Shipping" :value="$po->shipping_cost ?? 0" col="col-6" /></div>
            <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2"><span>Total</span><span id="po-total" class="money"></span></div>
        </div><div class="d-grid mt-3"><button class="btn btn-primary">Save purchase order</button></div></div>
    </div>
</form>
@endsection
