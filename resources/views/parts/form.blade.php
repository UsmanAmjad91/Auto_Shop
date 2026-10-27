@extends('layouts.app')
@section('title', $part->exists ? 'Edit '.$part->name : 'Add part')
@section('content')
<x-page-header :title="$part->exists ? 'Edit part' : 'Add part'" :breadcrumbs="['Parts' => route('parts.index'), ($part->exists ? $part->part_number : 'Add') => null]" />
<form method="POST" action="{{ $part->exists ? route('parts.update', $part) : route('parts.store') }}" enctype="multipart/form-data">
    @csrf @if($part->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-header">Part details</div><div class="card-body row g-3">
                <x-form.input name="name" label="Part name" :value="$part->name" required col="col-md-8" />
                <x-form.input name="part_number" label="Part ID" :value="$part->part_number" col="col-md-4" help="Blank = auto" />
                <x-form.input name="oem_number" label="OEM number" :value="$part->oem_number" col="col-md-4" />
                <x-form.input name="sku" label="SKU" :value="$part->sku" col="col-md-4" />
                <x-form.input name="barcode" label="Barcode" :value="$part->barcode" col="col-md-4" />
                <x-form.select name="category_id" label="Category" :options="$categories" :value="$part->category_id" placeholder="—" col="col-md-4" searchable />
                <x-form.input name="manufacturer" label="Manufacturer" :value="$part->manufacturer" col="col-md-4" />
                <x-form.input name="brand" label="Brand" :value="$part->brand" col="col-md-4" />
                <x-form.select name="condition" label="Condition" :options="config('autoshop.enums.part_conditions')" :value="$part->condition" required col="col-md-4" />
                <x-form.input name="unit" label="Unit" :value="$part->unit" required col="col-md-4" list="unit-list" />
                <datalist id="unit-list"><option value="pcs"><option value="set"><option value="litre"><option value="quart"><option value="yd"><option value="m"><option value="kit"></datalist>
                <x-form.remote name="supervisor_id" type="employees" label="Assigned supervisor" :value="$part->supervisor_id" :text="$part->supervisor?->full_name" col="col-md-4" />
                <x-form.textarea name="description" label="Description" :value="$part->description" col="col-12" />
            </div></div>

            <div class="card mb-3"><div class="card-header d-flex justify-content-between">Compatible vehicles <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="#compat-tpl"><i class="bi bi-plus"></i> Add</button></div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Make</th><th>Model</th><th>Year from</th><th>Year to</th><th></th></tr></thead>
                    <tbody id="compat-rows" data-repeater data-template="#compat-tpl">
                        @foreach(old('compat', $part->exists ? $part->compatibilities->toArray() : []) as $i => $c)
                            <tr data-row><td><input name="compat[{{ $i }}][make]" value="{{ $c['make'] ?? '' }}" class="form-control form-control-sm"></td><td><input name="compat[{{ $i }}][model]" value="{{ $c['model'] ?? '' }}" class="form-control form-control-sm"></td>
                                <td><input type="number" name="compat[{{ $i }}][year_from]" value="{{ $c['year_from'] ?? '' }}" class="form-control form-control-sm"></td><td><input type="number" name="compat[{{ $i }}][year_to]" value="{{ $c['year_to'] ?? '' }}" class="form-control form-control-sm"></td>
                                <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td></tr>
                        @endforeach
                    </tbody></table></div>
                <template id="compat-tpl"><tr data-row><td><input name="compat[__i__][make]" class="form-control form-control-sm" placeholder="Toyota"></td><td><input name="compat[__i__][model]" class="form-control form-control-sm" placeholder="Camry (blank = all)"></td>
                    <td><input type="number" name="compat[__i__][year_from]" class="form-control form-control-sm"></td><td><input type="number" name="compat[__i__][year_to]" class="form-control form-control-sm"></td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td></tr></template>
            </div>

            <div class="card mb-3"><div class="card-header">Purchasing & warranty</div><div class="card-body row g-3">
                <x-form.remote name="supplier_id" type="suppliers" label="Supplier" :value="$part->supplier_id" :text="$part->supplier?->name" col="col-md-6" :create="auth()->user()->can('suppliers.create') ? '#quickSupplierModal' : null" />
                <x-form.input name="purchase_date" type="date" label="Purchase date" :value="$part->purchase_date" col="col-md-3" />
                <x-form.input name="purchase_invoice" label="Purchase invoice #" :value="$part->purchase_invoice" col="col-md-3" />
                <x-form.input name="warranty_months" type="number" min="0" label="Warranty (months)" :value="$part->warranty_months" col="col-md-3" />
                <x-form.input name="warranty_terms" label="Warranty terms" :value="$part->warranty_terms" col="col-md-9" />
                <x-form.textarea name="notes" label="Notes" :value="$part->notes" rows="2" col="col-12" />
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header">Pricing & stock</div><div class="card-body row g-3">
                <x-form.input name="purchase_cost" type="number" step="0.01" min="0" label="Purchase cost" :value="$part->purchase_cost ?? 0" required col="col-6" :prefix="settings('general.currency_symbol')" />
                <x-form.input name="selling_price" type="number" step="0.01" min="0" label="Selling price" :value="$part->selling_price ?? 0" required col="col-6" :prefix="settings('general.currency_symbol')" />
                <x-form.input name="min_stock_level" type="number" min="0" label="Minimum stock level" :value="$part->min_stock_level" required col="col-6" />
                @unless($part->exists)<x-form.input name="initial_quantity" type="number" min="0" label="Opening quantity" value="0" col="col-6" help="Recorded as a stock movement" />@endunless
                <x-form.select name="default_location_id" label="Warehouse" :options="$locations" :value="$part->default_location_id" col="col-12" />
                <x-form.input name="shelf" label="Shelf" :value="$part->shelf" col="col-6" />
                <x-form.input name="bin" label="Bin" :value="$part->bin" col="col-6" />
                @can('parts.negative_stock')<x-form.switch name="allow_negative_stock" label="Allow negative stock for this part" :checked="$part->allow_negative_stock" col="col-12" help="Not recommended — stock could go below zero." />@endcan
                @if($part->exists)<div class="col-12 small text-secondary">Quantities change only through stock operations (on the part page) so every change is recorded.</div>@endif
            </div></div>
            <div class="card mb-3"><div class="card-header">Images</div><div class="card-body">
                <x-form.input name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" />
                @if($part->exists && $part->images->isNotEmpty())<div class="row g-2 mt-2 gallery">@foreach($part->images as $img)<div class="col-4"><img src="{{ $img->url() }}" alt=""></div>@endforeach</div>@endif
            </div></div>
            <div class="d-grid gap-2"><button class="btn btn-primary btn-lg">Save part</button><a href="{{ $part->exists ? route('parts.show', $part) : route('parts.index') }}" class="btn btn-light">Cancel</a></div>
        </div>
    </div>
</form>
@push('scripts')
<script type="module">
    // Lightweight repeater for compatibility rows (no remote selects needed here).
    const body = document.getElementById('compat-rows'); const tpl = document.getElementById('compat-tpl'); let i = 1000;
    document.querySelector('[data-add-row="#compat-tpl"]').addEventListener('click', () => body.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, i++)));
    body.addEventListener('click', (e) => { if (e.target.closest('[data-remove-row]')) e.target.closest('[data-row]').remove(); });
</script>
@endpush
@endsection
