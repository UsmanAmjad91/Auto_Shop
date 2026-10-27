<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\ReportService;
use App\Support\Exporter;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request)
    {
        $defs = collect(ReportService::definitions())->filter(fn ($d) => $this->allowed($request, $d));

        return view('reports.index', ['groups' => $defs->groupBy('group', true)]);
    }

    public function show(Request $request, string $report)
    {
        $def = $this->definition($request, $report);
        $result = $this->reports->run($report, $request);

        return view('reports.show', [
            'key' => $report, 'def' => $def, 'result' => $result,
            'departments' => in_array('department', $def['filters'], true) ? Department::orderBy('name')->pluck('name', 'id') : collect(),
            'selected' => [
                'vehicle' => $request->vehicle_id ? Vehicle::find($request->vehicle_id) : null,
                'customer' => $request->customer_id ? Customer::find($request->customer_id) : null,
                'employee' => $request->employee_id ? Employee::find($request->employee_id) : null,
            ],
        ]);
    }

    public function export(Request $request, string $report)
    {
        $def = $this->definition($request, $report);
        $result = $this->reports->run($report, $request);
        $rows = collect($result['rows'])->map(function ($row) use ($result) {
            foreach ($result['money'] as $k) {
                if (isset($row[$k]) && $row[$k] !== '') {
                    $row[$k] = round((float) $row[$k], 2);
                }
            }

            return $row;
        });
        $title = $def['title'].' '.$result['from']->format('Y-m-d').' to '.$result['to']->format('Y-m-d');

        return Exporter::download($request->input('format', 'csv'), $title, $result['columns'], $rows, $result['summary']);
    }

    protected function definition(Request $request, string $report): array
    {
        $def = ReportService::definitions()[$report] ?? abort(404);
        abort_unless($this->allowed($request, $def), 403);

        return $def;
    }

    protected function allowed(Request $request, array $def): bool
    {
        $user = $request->user();
        if ($def['financial'] && ! $user->can('financials.view')) {
            return false;
        }

        return ! isset($def['permission']) || $user->can($def['permission']);
    }
}
