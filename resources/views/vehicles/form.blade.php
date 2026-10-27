@extends('layouts.app')
@section('title', $vehicle->exists ? 'Edit '.$vehicle->stock_number : 'Add Vehicle')
@push('head') @vite('resources/js/vin-scanner.js') @endpush
@section('content')
<x-page-header :title="$vehicle->exists ? 'Edit vehicle '.$vehicle->stock_number : 'Add vehicle'"
    :breadcrumbs="['Vehicles' => route('vehicles.index'), ($vehicle->exists ? $vehicle->stock_number : 'Add') => null]" />

<form method="POST" action="{{ $vehicle->exists ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" enctype="multipart/form-data" id="vehicle-form" novalidate>
    @csrf @if($vehicle->exists) @method('PUT') @endif
    <input type="hidden" name="decoded_data" value="">
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-header">Identification</div><div class="card-body">
                <label for="vin" class="form-label required">VIN</label>
                <div class="input-group">
                    <input type="text" name="vin" id="vin" value="{{ old('vin', $vehicle->vin) }}" maxlength="17" required autocomplete="off"
                        class="form-control font-monospace text-uppercase @error('vin') is-invalid @enderror" placeholder="17-character VIN" data-decode-url="{{ route('vin.decode') }}">
                    <button type="button" class="btn btn-outline-secondary" id="vin-decode-btn"><i class="bi bi-search"></i> Decode</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vinScanModal"><i class="bi bi-upc-scan"></i> Scan VIN</button>
                </div>
                @error('vin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Scan the barcode/printed VIN with your camera, or type it and click Decode. Decoded details can be corrected before saving.</div>
                <div id="vin-decode-result" class="mt-2"></div>

                <div class="row g-3 mt-1">
                    <x-form.input name="stock_number" label="Stock number" :value="$vehicle->stock_number" col="col-md-4" help="Leave blank to auto-generate" />
                    <x-form.input name="make" label="Make" :value="$vehicle->make" required col="col-md-4" list="make-list" />
                    <x-form.input name="model" label="Model" :value="$vehicle->model" required col="col-md-4" />
                    <x-form.input name="year" type="number" label="Year" :value="$vehicle->year" required col="col-md-3" min="1900" :max="date('Y') + 2" />
                    <x-form.input name="trim" label="Trim" :value="$vehicle->trim" col="col-md-3" />
                    <x-form.select name="body_type" label="Body type" :options="array_combine($enums['body_types'], $enums['body_types'])" :value="$vehicle->body_type" required col="col-md-3" />
                    <x-form.input name="drive_type" label="Drive type" :value="$vehicle->drive_type" col="col-md-3" />
                    <x-form.input name="engine" label="Engine" :value="$vehicle->engine" col="col-md-4" />
                    <x-form.input name="transmission" label="Transmission" :value="$vehicle->transmission" col="col-md-4" list="trans-list" />
                    <x-form.input name="fuel_type" label="Fuel type" :value="$vehicle->fuel_type" col="col-md-4" list="fuel-list" />
                    <x-form.input name="exterior_color" label="Exterior colour" :value="$vehicle->exterior_color" col="col-md-4" />
                    <x-form.input name="interior_color" label="Interior colour" :value="$vehicle->interior_color" col="col-md-4" />
                    <x-form.input name="mileage" type="number" label="Mileage" :value="$vehicle->mileage" col="col-md-4" min="0" />
                </div>
                <datalist id="make-list">@foreach(\App\Models\Vehicle::distinct()->orderBy('make')->pluck('make') as $m)<option value="{{ $m }}">@endforeach</datalist>
                <datalist id="trans-list">@foreach($enums['transmissions'] as $t)<option value="{{ $t }}">@endforeach</datalist>
                <datalist id="fuel-list">@foreach($enums['fuel_types'] as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div></div>

            <div class="card mb-3"><div class="card-header">Registration</div><div class="card-body row g-3">
                <x-form.input name="license_plate" label="License plate" :value="$vehicle->license_plate" col="col-md-3" />
                <x-form.input name="registration_number" label="Registration #" :value="$vehicle->registration_number" col="col-md-3" />
                <x-form.input name="registration_state" label="State / region" :value="$vehicle->registration_state" col="col-md-3" />
                <x-form.input name="registration_expiry" type="date" label="Registration expiry" :value="$vehicle->registration_expiry" col="col-md-3" />
            </div></div>

            <div class="card mb-3"><div class="card-header">Acquisition & ownership</div><div class="card-body row g-3">
                <x-form.select name="ownership_type" label="Ownership type" :options="$enums['ownership_types']" :value="$vehicle->ownership_type" required col="col-md-4" />
                <div class="col-md-8" data-owner-only>
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-fill"><x-form.remote name="customer_id" type="customers" label="Client / owner" :value="$vehicle->customer_id" :text="$vehicle->owner?->display_name" create="#quickCustomerModal" /></div>
                    </div>
                </div>
                <x-form.input name="purchase_date" type="date" label="Purchase / intake date" :value="$vehicle->purchase_date" col="col-md-4" />
                @can('vehicles.view_costs')
                    <x-form.input name="purchase_price" type="number" step="0.01" min="0" label="Purchase price" :value="$vehicle->purchase_price" col="col-md-4" :prefix="settings('general.currency_symbol')" />
                @else
                    <input type="hidden" name="purchase_price" value="{{ $vehicle->purchase_price }}">
                @endcan
                <x-form.select name="purchase_source" label="Purchase source" :options="$enums['purchase_sources']" :value="$vehicle->purchase_source" placeholder="—" col="col-md-4" />
                <x-form.remote name="supplier_id" type="suppliers" label="Supplier / seller" :value="$vehicle->supplier_id" :text="$vehicle->supplier?->name" col="col-md-6" :create="auth()->user()->can('suppliers.create') ? '#quickSupplierModal' : null" />
                <x-form.select name="condition" label="Condition" :options="$enums['conditions']" :value="$vehicle->condition" placeholder="—" col="col-md-3" />
                <x-form.input name="location" label="Location" :value="$vehicle->location" col="col-md-3" placeholder="Lot / bay" />
            </div></div>

            <div class="card mb-3"><div class="card-header">Description & notes</div><div class="card-body row g-3">
                <x-form.textarea name="description" label="Description (customer facing)" :value="$vehicle->description" rows="3" col="col-12" />
                <x-form.textarea name="notes" label="Internal notes" :value="$vehicle->notes" rows="2" col="col-12" />
            </div></div>

            @if($customFields->isNotEmpty())
            <div class="card mb-3"><div class="card-header">Additional fields</div><div class="card-body row g-3">
                @foreach($customFields as $f)
                    @php $n = 'custom_fields['.$f->key.']'; $v = $vehicle->custom_fields[$f->key] ?? null; @endphp
                    @switch($f->type)
                        @case('select') <x-form.select :name="$n" :label="$f->label" :options="array_combine($f->options ?? [], $f->options ?? [])" :value="$v" placeholder="—" :required="$f->is_required" col="col-md-4" /> @break
                        @case('textarea') <x-form.textarea :name="$n" :label="$f->label" :value="$v" :required="$f->is_required" col="col-12" /> @break
                        @case('checkbox') <x-form.switch :name="$n" :label="$f->label" :checked="(bool) $v" col="col-md-4" /> @break
                        @default <x-form.input :name="$n" :type="$f->type" :label="$f->label" :value="$v" :required="$f->is_required" col="col-md-4" />
                    @endswitch
                @endforeach
            </div></div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header">Status & pricing</div><div class="card-body row g-3">
                <x-form.select name="repair_status" label="Repair status" :options="$statuses" :value="$vehicle->repair_status" col="col-12" />
                <x-form.select name="inventory_status" label="Inventory status" :options="$enums['inventory_statuses']" :value="$vehicle->inventory_status" col="col-12"
                    help="Vehicles are marked Sold automatically when a sale invoice is issued." />
                <x-form.input name="asking_price" type="number" step="0.01" min="0" label="Asking price" :value="$vehicle->asking_price" col="col-12" :prefix="settings('general.currency_symbol')" />
                <x-form.remote name="assigned_employee_id" type="employees" label="Assigned employee" :value="$vehicle->assigned_employee_id" :text="$vehicle->assignedEmployee?->full_name" col="col-12" />
            </div></div>
            <div class="card mb-3"><div class="card-header">Photos & documents</div><div class="card-body">
                <x-form.input name="images[]" type="file" label="Photos (multiple)" multiple accept="image/jpeg,image/png,image/webp" help="JPG/PNG/WebP, max {{ config('autoshop.uploads.image_max_kb') / 1024 }} MB each" />
                <div class="mt-3"><x-form.input name="documents[]" type="file" label="Documents" multiple help="PDF, images, Office files — stored privately" /></div>
                @if($vehicle->exists && $vehicle->images->isNotEmpty())
                    <div class="row g-2 mt-2 gallery">@foreach($vehicle->images as $img)<div class="col-4"><img src="{{ $img->url() }}" alt=""></div>@endforeach</div>
                    <div class="form-text">Manage photos on the vehicle page.</div>
                @endif
            </div></div>
            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> {{ $vehicle->exists ? 'Save changes' : 'Add vehicle' }}</button>
                <a href="{{ $vehicle->exists ? route('vehicles.show', $vehicle) : route('vehicles.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </div>
    </div>
</form>

@push('modals')
<div class="modal fade" id="vinScanModal" tabindex="-1" aria-labelledby="vinScanTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="vinScanTitle"><i class="bi bi-upc-scan me-1"></i> Scan VIN</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="btn-group w-100 mb-2" role="group" aria-label="Scan mode">
                <input type="radio" class="btn-check" name="vin-mode" id="vin-mode-barcode" value="barcode" checked><label class="btn btn-outline-primary" for="vin-mode-barcode"><i class="bi bi-upc"></i> Barcode</label>
                <input type="radio" class="btn-check" name="vin-mode" id="vin-mode-ocr" value="ocr"><label class="btn btn-outline-primary" for="vin-mode-ocr"><i class="bi bi-fonts"></i> Printed text (OCR)</label>
            </div>
            <div id="vin-scan-status" class="alert alert-secondary small py-2 mb-2">Allow camera access when prompted.</div>
            <div id="vin-barcode-pane"><div id="vin-reader"></div></div>
            <div id="vin-ocr-pane" class="d-none">
                <div class="position-relative"><video id="vin-video" class="vin-scanner-video" playsinline muted></video><div class="scan-guide"></div></div>
                <button type="button" class="btn btn-primary w-100 mt-2" id="vin-ocr-read"><i class="bi bi-camera"></i> Read VIN</button>
                <div class="form-text">The OCR engine (~10 MB) downloads on first use. Good lighting and a straight angle work best.</div>
            </div>
            <hr>
            <label class="form-label small" for="vin-manual">Scanning not working? Enter the VIN manually:</label>
            <div class="input-group"><input id="vin-manual" class="form-control font-monospace text-uppercase" maxlength="20" placeholder="1HGCM82633A004352"><button type="button" class="btn btn-outline-secondary" id="vin-manual-use">Use VIN</button></div>
            <div class="form-text">Camera access requires HTTPS (or localhost) and permission from the browser.</div>
        </div>
    </div></div>
</div>
@endpush
@endsection
