@extends('layouts.app')
@section('title', $job->exists ? 'Edit '.$job->job_number : 'New service job')
@section('content')
@php $types = \App\Models\ServiceType::where('category', $job->category)->where('is_active', true)->orderBy('name')->get(); @endphp
<x-page-header :title="$job->exists ? 'Edit job '.$job->job_number : 'New '.strtolower(config('autoshop.enums.service_categories.'.$job->category)).' job'"
    :breadcrumbs="[config('autoshop.enums.service_categories.'.$job->category) => route('service-jobs.index', ['category' => $job->category]), ($job->exists ? $job->job_number : 'New') => null]" />
<form method="POST" action="{{ $job->exists ? route('service-jobs.update', $job) : route('service-jobs.store') }}">
    @csrf @if($job->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-body row g-3">
                @unless($job->exists)
                    <x-form.select name="category" label="Category" :options="config('autoshop.enums.service_categories')" :value="$job->category" required col="col-md-4"
                        onchange="window.location='{{ route('service-jobs.create') }}?category='+this.value+'&vehicle_id={{ $job->vehicle_id }}'" />
                    <x-form.remote name="vehicle_id" type="vehicles" label="Vehicle" :value="$job->vehicle_id" :text="$job->vehicle ? $job->vehicle->title().' ('.$job->vehicle->stock_number.')' : null" required col="col-md-8" />
                @else
                    <div class="col-12"><span class="text-secondary small">Vehicle</span><div class="fw-semibold">{{ $job->vehicle?->title() }} · {{ $job->vehicle?->vin }}</div></div>
                @endunless
                <x-form.input name="title" label="Job title" :value="$job->title" required col="col-md-8" placeholder="e.g. Front brake replacement" />
                <x-form.select name="priority" label="Priority" :options="config('autoshop.enums.priorities')" :value="$job->priority" col="col-md-4" />
                <x-form.textarea name="description" label="Description / customer complaint" :value="$job->description" rows="3" col="col-12" />
                <x-form.remote name="customer_id" type="customers" label="Customer (for billing)" :value="$job->customer_id" :text="$job->customer?->display_name" col="col-md-6" create="#quickCustomerModal"
                    help="Leave blank for internal work on company stock." />
                <x-form.input name="odometer_in" type="number" min="0" label="Odometer in" :value="$job->odometer_in" col="col-md-3" />
                <div class="col-md-3 d-flex align-items-end"><x-form.switch name="is_billable" label="Billable to customer" :checked="$job->is_billable" /></div>
                @unless($job->exists)
                <div class="col-12"><label class="form-label">Services to perform</label>
                    <select name="service_type_ids[]" multiple data-searchable class="form-select">
                        @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select><div class="form-text">Adds a line per service using its default price/cost (editable later). Configure in Settings → Lookups.</div></div>
                @endunless
                <x-form.textarea name="notes" label="Internal notes" :value="$job->notes" rows="2" col="col-12" />
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-body row g-3">
                <x-form.select name="status" label="Status" :options="config('autoshop.enums.service_statuses')" :value="$job->status" col="col-12" />
                <x-form.input name="scheduled_date" type="date" label="Scheduled" :value="$job->scheduled_date" col="col-12" />
                <x-form.input name="start_date" type="datetime-local" label="Started" :value="$job->start_date" col="col-6" />
                <x-form.input name="completion_date" type="datetime-local" label="Completed" :value="$job->completion_date" col="col-6" />
                <x-form.remote name="supervisor_id" type="employees" label="Supervisor" :value="$job->supervisor_id" :text="$job->supervisor?->full_name" col="col-12" />
                <x-form.input name="customer_charge" type="number" step="0.01" min="0" label="Customer charge (if no line items)" :value="$job->customer_charge" col="col-12" help="When lines exist, the charge is the sum of line prices." />
            </div></div>
            <div class="card mb-3"><div class="card-header">Assigned employees</div><div class="card-body" id="job-employees">
                @php $assignedRows = old('employees') ? collect(old('employees'))->map(fn ($p, $id) => ['id' => $id, 'text' => \App\Models\Employee::find($id)?->full_name] + $p)->values()->all() : $assigned; @endphp
                @foreach($assignedRows as $a)
                    <div class="row g-1 mb-2 emp-row"><div class="col-6 small fw-semibold align-self-center">{{ $a['text'] }}</div>
                        <div class="col-3"><input name="employees[{{ $a['id'] }}][role]" value="{{ $a['role'] ?? 'technician' }}" class="form-control form-control-sm" aria-label="Role"></div>
                        <div class="col-2"><input name="employees[{{ $a['id'] }}][hours_worked]" value="{{ $a['hours_worked'] ?? 0 }}" type="number" step="0.25" min="0" class="form-control form-control-sm" aria-label="Hours"></div>
                        <div class="col-1"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.closest('.emp-row').remove()"><i class="bi bi-x-lg"></i></button></div></div>
                @endforeach
                <select id="add-employee" data-remote="employees" data-placeholder="Add employee…" class="form-select form-select-sm"></select>
                <div class="form-text">Role and hours per employee. Multiple employees can work on one job.</div>
            </div></div>
            <div class="d-grid gap-2"><button class="btn btn-primary btn-lg">Save job</button></div>
        </div>
    </div>
</form>
@push('scripts')
<script type="module">
    const sel = document.getElementById('add-employee');
    sel.addEventListener('remote:selected', (e) => {
        const d = e.detail; if (!d) return;
        const box = document.getElementById('job-employees');
        if (box.querySelector(`[name="employees[${d.id}][role]"]`)) { sel.tomselect.clear(true); return; }
        const row = document.createElement('div'); row.className = 'row g-1 mb-2 emp-row';
        row.innerHTML = `<div class="col-6 small fw-semibold align-self-center"></div>
            <div class="col-3"><input name="employees[${d.id}][role]" value="technician" class="form-control form-control-sm"></div>
            <div class="col-2"><input name="employees[${d.id}][hours_worked]" value="0" type="number" step="0.25" min="0" class="form-control form-control-sm"></div>
            <div class="col-1"><button type="button" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></div>`;
        row.querySelector('.col-6').textContent = d.text;
        row.querySelector('button').addEventListener('click', () => row.remove());
        sel.closest('.ts-wrapper').before(row);
        sel.tomselect.clear(true);
    });
</script>
@endpush
@endsection
