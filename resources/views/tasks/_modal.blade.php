@push('modals')
<x-modal id="taskModal" title="Assign task">
    <form method="POST" action="{{ route('tasks.store') }}" data-ajax data-reset data-table-reload="#tasks-table">@csrf
        <div class="row g-2">
            <x-form.input name="title" label="Task" required col="col-12" />
            <x-form.textarea name="description" label="Details" rows="2" col="col-12" />
            <x-form.remote name="employee_id" type="employees" label="Assign to" :value="$employeeId ?? null" :text="$employeeName ?? null" required col="col-md-6" />
            <x-form.select name="priority" label="Priority" :options="config('autoshop.enums.priorities')" value="normal" col="col-md-3" />
            <x-form.input name="due_date" type="date" label="Due" col="col-md-3" />
            <x-form.remote name="vehicle_id" type="vehicles" label="Vehicle (optional)" :value="$vehicleId ?? null" :text="$vehicleName ?? null" col="col-md-6" />
            <x-form.remote name="service_job_id" type="service_jobs" label="Service job (optional)" :value="$jobId ?? null" :text="$jobName ?? null" col="col-md-6" />
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary">Assign</button></div>
    </form>
</x-modal>
@endpush
