<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceJob;
use App\Services\ServiceJobService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceJobApiController extends Controller
{
    public function index(Request $request)
    {
        return ServiceJob::visibleTo($request->user())->with('vehicle:id,vin,stock_number,year,make,model')
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(min(100, max(1, (int) $request->input('per_page', 25))))
            ->through(fn ($j) => $j->only(['id', 'job_number', 'category', 'title', 'status', 'priority', 'scheduled_date', 'vehicle']));
    }

    public function show(ServiceJob $job)
    {
        $this->authorize('view', $job);

        return $job->load(['vehicle:id,vin,stock_number,year,make,model', 'items:id,service_job_id,item_type,description,quantity', 'employees:id,first_name,last_name'])
            ->only(['id', 'job_number', 'category', 'title', 'description', 'status', 'priority', 'scheduled_date', 'start_date', 'completion_date', 'checklist', 'vehicle', 'items', 'employees']);
    }

    public function status(Request $request, ServiceJob $job, ServiceJobService $jobs)
    {
        $this->authorize('update', $job);
        $data = $request->validate(['status' => ['required', Rule::in(['scheduled', 'in_progress', 'on_hold', 'completed'])], 'notes' => ['nullable', 'string', 'max:500']]);
        $jobs->changeStatus($job, $data['status'], $data['notes'] ?? null);

        return ['message' => 'Status updated.', 'status' => $job->fresh()->status];
    }
}
