<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Models\CustomField;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleExpense;
use App\Models\VehicleImage;
use App\Models\VehicleInspection;
use App\Models\VehicleStatus;
use App\Models\VehicleStatusHistory;
use App\Services\AuditLogger;
use App\Services\VehicleCostService;
use App\Support\Barcode;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\Money;
use App\Support\NumberGenerator;
use App\Support\Uploads;
use App\Support\Vin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VehicleController extends Controller
{
    public function __construct(protected VehicleCostService $costs) {}

    /* ------------------------------------------------------------------ list */

    protected function table(Request $request): DataTable
    {
        return DataTable::make(Vehicle::query()->with(['primaryImage', 'assignedEmployee']), $request)
            ->searchable(['vin', 'stock_number', 'make', 'model', 'trim', 'license_plate'])
            ->sortable([
                'id' => 'id', 'stock_number' => 'stock_number', 'vin' => 'vin', 'make' => 'make', 'model' => 'model',
                'year' => 'year', 'mileage' => 'mileage', 'purchase_price' => 'purchase_price', 'total_investment' => 'total_investment',
                'asking_price' => 'asking_price', 'sale_price' => 'sale_price', 'created_at' => 'created_at', 'purchase_date' => 'purchase_date',
                'repair_status' => 'repair_status', 'inventory_status' => 'inventory_status',
            ], 'created_at')
            ->filters(function ($q, Request $r) {
                $canCosts = $r->user()->can('vehicles.view_costs');
                $q->when($r->vin, fn ($x) => $x->where('vin', 'like', '%'.Vin::normalize($r->vin).'%'))
                    ->when($r->stock_number, fn ($x) => $x->where('stock_number', 'like', '%'.$r->stock_number.'%'))
                    ->when($r->make, fn ($x) => $x->where('make', $r->make))
                    ->when($r->model, fn ($x) => $x->where('model', 'like', '%'.$r->model.'%'))
                    ->when($r->year_from, fn ($x) => $x->where('year', '>=', (int) $r->year_from))
                    ->when($r->year_to, fn ($x) => $x->where('year', '<=', (int) $r->year_to))
                    ->when($r->body_type, fn ($x) => $x->where('body_type', $r->body_type))
                    ->when($r->ownership_type, fn ($x) => $x->where('ownership_type', $r->ownership_type))
                    ->when($canCosts && is_numeric($r->purchase_min), fn ($x) => $x->where('purchase_price', '>=', (float) $r->purchase_min))
                    ->when($canCosts && is_numeric($r->purchase_max), fn ($x) => $x->where('purchase_price', '<=', (float) $r->purchase_max))
                    ->when(is_numeric($r->asking_min), fn ($x) => $x->where('asking_price', '>=', (float) $r->asking_min))
                    ->when(is_numeric($r->asking_max), fn ($x) => $x->where('asking_price', '<=', (float) $r->asking_max))
                    ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                    ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to))
                    ->when($r->repair_status, fn ($x) => $x->where('repair_status', $r->repair_status))
                    ->when($r->inventory_status, fn ($x) => $x->where('inventory_status', $r->inventory_status),
                        fn ($x) => $x->where('inventory_status', '!=', 'archived'))
                    ->when($r->payment_status, fn ($x) => $x->where('payment_status', $r->payment_status))
                    ->when($r->assigned_employee_id, fn ($x) => $x->where('assigned_employee_id', $r->assigned_employee_id));
            });
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('vehicles.index', [
                'statuses' => VehicleStatus::options(),
                'makes' => Vehicle::distinct()->orderBy('make')->pluck('make'),
                'employees' => Employee::where('employment_status', '!=', 'terminated')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
                'years' => Vehicle::distinct()->orderByDesc('year')->pluck('year'),
            ]);
        }

        $user = $request->user();
        $costs = $user->can('vehicles.view_costs');
        $statusNames = VehicleStatus::options();
        $statusColors = VehicleStatus::pluck('color', 'slug');

        return $this->table($request)->transform(function (Vehicle $v) use ($user, $costs, $statusNames, $statusColors) {
            $profit = $v->profit();

            return [
                'id' => $v->id, 'url' => route('vehicles.show', $v),
                'image' => $v->primaryImage ? asset('storage/'.$v->primaryImage->path) : null,
                'vin' => $v->vin, 'stock_number' => $v->stock_number, 'make' => $v->make, 'model' => $v->model, 'year' => $v->year,
                'trim' => $v->trim, 'title' => $v->title(), 'body_type' => $v->body_type, 'mileage' => $v->mileage !== null ? number_format($v->mileage) : '',
                'purchase_price' => $costs ? (float) $v->purchase_price : null,
                'repair_cost' => $costs ? Money::add($v->parts_cost, $v->labor_cost, $v->interior_cost, $v->detailing_cost) : null,
                'total_investment' => $costs ? (float) $v->total_investment : null,
                'asking_price' => $v->asking_price !== null ? (float) $v->asking_price : null,
                'sale_price' => $v->sale_price !== null ? (float) $v->sale_price : null,
                'profit' => $costs ? $profit : null,
                'profit_estimated' => $v->isProfitEstimated(),
                'repair_status' => ['label' => $statusNames[$v->repair_status] ?? status_label($v->repair_status), 'color' => $statusColors[$v->repair_status] ?? 'secondary'],
                'payment_status' => ['label' => config('autoshop.enums.vehicle_payment_statuses.'.$v->payment_status, status_label($v->payment_status)), 'color' => status_color($v->payment_status)],
                'inventory_status' => ['label' => status_label($v->inventory_status), 'color' => status_color($v->inventory_status)],
                'ownership' => config('autoshop.enums.ownership_types.'.$v->ownership_type),
                'created_at' => fmt_date($v->created_at),
                'assigned' => $v->assignedEmployee?->full_name,
                'actions' => array_values(array_filter([
                    ['label' => 'View', 'icon' => 'eye', 'url' => route('vehicles.show', $v)],
                    $user->can('vehicles.edit') ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('vehicles.edit', $v)] : null,
                    $user->can('vehicles.print') ? ['label' => 'Info sheet', 'icon' => 'printer', 'url' => route('vehicles.sheet', $v), 'target' => '_blank'] : null,
                    $user->can('vehicles.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('vehicles.destroy', $v), 'method' => 'DELETE', 'confirm' => "Delete vehicle {$v->stock_number}?", 'danger' => true] : null,
                ])),
            ];
        })->response();
    }

    public function export(Request $request)
    {
        $costs = $request->user()->can('vehicles.view_costs');
        $names = VehicleStatus::options();
        $columns = ['id' => 'ID', 'stock_number' => 'Stock #', 'vin' => 'VIN', 'year' => 'Year', 'make' => 'Make', 'model' => 'Model', 'trim' => 'Trim',
            'body_type' => 'Type', 'mileage' => 'Mileage', 'ownership' => 'Ownership']
            + ($costs ? ['purchase_price' => 'Purchase Price', 'repair_cost' => 'Repair Cost', 'other_cost' => 'Other Expenses', 'total_investment' => 'Total Investment'] : [])
            + ['asking_price' => 'Asking Price', 'sale_price' => 'Sale Price'] + ($costs ? ['profit' => 'Profit / Loss'] : [])
            + ['repair_status' => 'Repair Status', 'payment_status' => 'Payment Status', 'inventory_status' => 'Inventory Status',
                'assigned' => 'Assigned Employee', 'created_at' => 'Date Added'];

        $rows = $this->table($request)->all()->map(fn (Vehicle $v) => [
            'id' => $v->id, 'stock_number' => $v->stock_number, 'vin' => $v->vin, 'year' => $v->year, 'make' => $v->make, 'model' => $v->model,
            'trim' => $v->trim, 'body_type' => $v->body_type, 'mileage' => $v->mileage, 'ownership' => config('autoshop.enums.ownership_types.'.$v->ownership_type),
            'purchase_price' => $v->purchase_price, 'repair_cost' => Money::add($v->parts_cost, $v->labor_cost, $v->interior_cost, $v->detailing_cost),
            'other_cost' => $v->other_cost, 'total_investment' => $v->total_investment, 'asking_price' => $v->asking_price, 'sale_price' => $v->sale_price,
            'profit' => $v->profit(), 'repair_status' => $names[$v->repair_status] ?? $v->repair_status, 'payment_status' => status_label($v->payment_status),
            'inventory_status' => status_label($v->inventory_status), 'assigned' => $v->assignedEmployee?->full_name, 'created_at' => fmt_date($v->created_at),
        ]);

        return Exporter::download($request->input('format', 'csv'), 'Vehicle Inventory', $columns, $rows);
    }

    /* ------------------------------------------------------------------ CRUD */

    public function create()
    {
        return view('vehicles.form', $this->formData(new Vehicle([
            'ownership_type' => 'company', 'body_type' => 'Other', 'repair_status' => 'not_started', 'inventory_status' => 'available',
            'purchase_date' => today(),
        ])));
    }

    public function store(VehicleRequest $request)
    {
        $data = $request->validated();

        $vehicle = DB::transaction(function () use ($request, $data) {
            $vehicle = new Vehicle($this->fillable($data));
            $vehicle->stock_number = ($data['stock_number'] ?? null) ?: NumberGenerator::next('stock', settings('general.stock_prefix', 'STK-'), 5);
            $vehicle->repair_status = $data['repair_status'] ?? 'not_started';
            $vehicle->inventory_status = $data['inventory_status'] ?? 'available';
            $vehicle->purchase_price = $request->user()->can('vehicles.view_costs') ? ($data['purchase_price'] ?? 0) : 0;
            $vehicle->created_by = $request->user()->id;
            $vehicle->save();

            VehicleStatusHistory::create(['vehicle_id' => $vehicle->id, 'field' => 'repair_status', 'to_status' => $vehicle->repair_status,
                'notes' => 'Vehicle added to inventory', 'changed_by' => $request->user()->id]);

            $this->saveUploads($request, $vehicle);
            $this->costs->recalculate($vehicle);

            return $vehicle;
        });

        return redirect()->route('vehicles.show', $vehicle)->with('success', "Vehicle {$vehicle->stock_number} added.");
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        $vehicle->load(['images', 'documents.uploader', 'expenses.supplier', 'inspections.inspector', 'statusHistory.user', 'statusHistory.employee',
            'serviceJobs.employees', 'invoices', 'owner', 'buyer', 'supplier', 'assignedEmployee', 'saleInvoice.financing']);

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'statuses' => VehicleStatus::options(),
            'statusColors' => VehicleStatus::pluck('color', 'slug'),
            'customFields' => CustomField::forModule('vehicles'),
            'canCosts' => $request->user()->can('vehicles.view_costs'),
            'qr' => (string) QrCode::format('svg')->size(140)->margin(0)->generate(route('vehicles.show', $vehicle)),
            'parts' => $vehicle->stockMovements()->with('part')->whereIn('type', ['job_issue', 'job_return', 'sale'])->latest('id')->get(),
        ]);
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.form', $this->formData($vehicle));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $vehicle, $data) {
            $repair = $data['repair_status'] ?? $vehicle->repair_status;
            $inventory = $data['inventory_status'] ?? $vehicle->inventory_status;
            if ($vehicle->sale_invoice_id && $inventory !== $vehicle->inventory_status && ! in_array($inventory, ['sold', 'delivered'], true)) {
                abort(422, 'This vehicle was sold on an invoice. Void the invoice to return it to inventory.');
            }

            $vehicle->fill($this->fillable($data));
            if (! empty($data['stock_number'])) {
                $vehicle->stock_number = $data['stock_number'];
            }
            if ($request->user()->can('vehicles.view_costs')) {
                $vehicle->purchase_price = $data['purchase_price'] ?? 0;
            }
            $vehicle->save();

            $this->costs->changeStatus($vehicle, 'repair_status', $repair, 'Updated via edit form');
            $this->costs->changeStatus($vehicle, 'inventory_status', $inventory, 'Updated via edit form');
            $this->saveUploads($request, $vehicle);
            $this->costs->recalculate($vehicle);
        });

        return redirect()->route('vehicles.show', $vehicle)->with('success', 'Vehicle updated.');
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->invoices()->whereNotIn('status', ['void'])->exists()) {
            return $this->fail($request, 'This vehicle has invoices. Void them first, or archive the vehicle instead.');
        }
        if ($vehicle->serviceJobs()->whereNotIn('status', ['cancelled', 'delivered'])->exists()) {
            return $this->fail($request, 'This vehicle has active service jobs.');
        }
        $vehicle->delete();

        return $this->done($request, 'Vehicle deleted.', route('vehicles.index'));
    }

    protected function fail(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->withErrors(['vehicle' => $message]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', 'in:delete,repair_status,inventory_status,assign'],
            'value' => ['nullable', 'string', 'max:60'],
        ]);
        $perm = match ($data['action']) {
            'delete' => 'vehicles.delete', 'repair_status' => 'vehicles.change_status', default => 'vehicles.edit',
        };
        abort_unless($request->user()->can($perm), 403);

        $n = 0;
        DB::transaction(function () use ($data, &$n) {
            foreach (Vehicle::whereIn('id', $data['ids'])->get() as $v) {
                match ($data['action']) {
                    'delete' => $v->invoices()->where('status', '!=', 'void')->exists() ? null : $v->delete(),
                    'repair_status' => array_key_exists((string) $data['value'], VehicleStatus::options()) ? $this->costs->changeStatus($v, 'repair_status', $data['value'], 'Bulk update') : null,
                    'inventory_status' => array_key_exists((string) $data['value'], config('autoshop.enums.inventory_statuses')) && ! $v->sale_invoice_id
                        ? $this->costs->changeStatus($v, 'inventory_status', $data['value'], 'Bulk update') : null,
                    'assign' => $v->forceFill(['assigned_employee_id' => Employee::find($data['value'])?->id])->save(),
                };
                $n++;
            }
        });

        return $this->done($request, "Bulk action applied to {$n} vehicle(s).");
    }

    protected function fillable(array $data): array
    {
        $out = collect($data)->except(['images', 'documents', 'stock_number', 'repair_status', 'inventory_status', 'decoded_data', 'purchase_price'])->all();
        if (! empty($data['decoded_data'])) {
            $out['decoded_data'] = json_decode($data['decoded_data'], true);
        }

        return $out;
    }

    protected function formData(Vehicle $vehicle): array
    {
        return [
            'vehicle' => $vehicle,
            'statuses' => VehicleStatus::options(),
            'customFields' => CustomField::forModule('vehicles'),
            'enums' => config('autoshop.enums'),
        ];
    }

    protected function saveUploads(Request $request, Vehicle $vehicle): void
    {
        $hasPrimary = $vehicle->images()->where('is_primary', true)->exists();
        foreach ((array) $request->file('images', []) as $i => $file) {
            $vehicle->images()->create([
                'path' => Uploads::image($file, "vehicles/{$vehicle->id}"),
                'is_primary' => ! $hasPrimary && $i === 0,
                'sort_order' => $vehicle->images()->count(),
            ]);
        }
        foreach ((array) $request->file('documents', []) as $file) {
            $vehicle->documents()->create([
                'document_type' => 'other', 'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'path' => Uploads::private($file, "vehicle-documents/{$vehicle->id}"),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /* ------------------------------------------------------------------ status */

    public function status(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'field' => ['required', 'in:repair_status,inventory_status'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
        $allowed = $data['field'] === 'repair_status' ? array_keys(VehicleStatus::options()) : array_keys(config('autoshop.enums.inventory_statuses'));
        abort_unless(in_array($data['status'], $allowed, true), 422, 'Invalid status.');
        if ($data['field'] === 'inventory_status') {
            abort_unless($request->user()->can('vehicles.edit'), 403);
            abort_if($vehicle->sale_invoice_id && ! in_array($data['status'], ['sold', 'delivered'], true), 422, 'Void the sale invoice to return this vehicle to inventory.');
        }
        $this->costs->changeStatus($vehicle, $data['field'], $data['status'], $data['notes'] ?? null, $data['employee_id'] ?? null);

        return $this->done($request, 'Status updated.');
    }

    /* ------------------------------------------------------------------ images & documents */

    public function uploadImages(Request $request, Vehicle $vehicle)
    {
        $request->validate(['images' => ['required', 'array', 'max:20'], 'images.*' => Uploads::imageRule(true)]);
        $this->saveUploads($request, $vehicle);

        return back()->with('success', 'Images uploaded.');
    }

    public function primaryImage(Vehicle $vehicle, VehicleImage $image)
    {
        abort_unless($image->vehicle_id === $vehicle->id, 404);
        $vehicle->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'Primary image set.');
    }

    public function destroyImage(Vehicle $vehicle, VehicleImage $image)
    {
        abort_unless($image->vehicle_id === $vehicle->id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();
        if (! $vehicle->images()->where('is_primary', true)->exists()) {
            $vehicle->images()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Image deleted.');
    }

    public function uploadDocument(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(VehicleDocument::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'expires_at' => ['nullable', 'date'],
            'file' => Uploads::documentRule(true),
        ]);
        $file = $request->file('file');
        $vehicle->documents()->create([
            'document_type' => $data['document_type'], 'title' => $data['title'], 'expires_at' => $data['expires_at'] ?? null,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'path' => Uploads::private($file, "vehicle-documents/{$vehicle->id}"),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function downloadDocument(Vehicle $vehicle, VehicleDocument $document)
    {
        abort_unless($document->vehicle_id === $vehicle->id && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(Vehicle $vehicle, VehicleDocument $document)
    {
        abort_unless($document->vehicle_id === $vehicle->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    /* ------------------------------------------------------------------ expenses */

    public function storeExpense(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('autoshop.enums.expense_categories')))],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'expense_date' => ['required', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($vehicle, $data, $request) {
            $vehicle->expenses()->create($data + ['created_by' => $request->user()->id]);
            $this->costs->recalculate($vehicle);
        });

        return back()->with('success', 'Expense recorded.');
    }

    public function destroyExpense(Vehicle $vehicle, VehicleExpense $expense)
    {
        abort_unless($expense->vehicle_id === $vehicle->id, 404);
        DB::transaction(function () use ($vehicle, $expense) {
            $expense->delete();
            $this->costs->recalculate($vehicle);
        });

        return back()->with('success', 'Expense removed.');
    }

    /* ------------------------------------------------------------------ inspections */

    public function createInspection(Vehicle $vehicle)
    {
        return view('vehicles.inspection-form', ['vehicle' => $vehicle, 'items' => config('autoshop.inspection_checklist')]);
    }

    public function storeInspection(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'inspection_date' => ['required', 'date'],
            'type' => ['required', 'in:intake,pre_sale,quality,other'],
            'inspector_id' => ['nullable', 'exists:employees,id'],
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.status' => ['required', 'in:pass,fail,na'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $result = collect($data['items'])->contains('status', 'fail') ? 'fail' : 'pass';
        $inspection = $vehicle->inspections()->create($data + ['result' => $result, 'items' => array_values($data['items'])]);
        AuditLogger::log('inspection', $vehicle, "Inspection ({$data['type']}) recorded: {$result}");

        return redirect()->route('vehicles.inspections.show', [$vehicle, $inspection])->with('success', 'Inspection saved.');
    }

    public function showInspection(Vehicle $vehicle, VehicleInspection $inspection)
    {
        abort_unless($inspection->vehicle_id === $vehicle->id, 404);

        return view('vehicles.inspection-show', ['vehicle' => $vehicle, 'inspection' => $inspection->load('inspector')]);
    }

    /* ------------------------------------------------------------------ printables */

    public function label(Vehicle $vehicle)
    {
        return view('vehicles.label', [
            'vehicle' => $vehicle,
            'qr' => (string) QrCode::format('svg')->size(150)->margin(0)->generate(route('vehicles.show', $vehicle)),
            'barcode' => Barcode::code39Svg($vehicle->vin, 45, 1.1),
        ]);
    }

    public function sheet(Request $request, Vehicle $vehicle)
    {
        $vehicle->load('images');
        $data = [
            'vehicle' => $vehicle,
            'customFields' => CustomField::forModule('vehicles'),
            'qr' => (string) QrCode::format('svg')->size(110)->margin(0)->generate(route('vehicles.show', $vehicle)),
            'pdf' => $request->boolean('pdf'),
        ];
        if ($request->boolean('pdf')) {
            $img = $vehicle->images->first();
            $data['image'] = $img && Storage::disk('public')->exists($img->path)
                ? 'data:'.(Storage::disk('public')->mimeType($img->path) ?: 'image/jpeg').';base64,'.base64_encode(Storage::disk('public')->get($img->path)) : null;
            $data['qr'] = 'data:image/svg+xml;base64,'.base64_encode($data['qr']);

            return Pdf::loadView('vehicles.sheet', $data)->download("vehicle-{$vehicle->stock_number}.pdf");
        }

        return view('vehicles.sheet', $data);
    }

    /* ------------------------------------------------------------------ import */

    public const IMPORT_COLUMNS = ['vin', 'stock_number', 'make', 'model', 'year', 'trim', 'engine', 'transmission', 'fuel_type', 'body_type',
        'exterior_color', 'interior_color', 'mileage', 'license_plate', 'purchase_date', 'purchase_price', 'purchase_source',
        'ownership_type', 'condition', 'location', 'asking_price', 'notes'];

    public function importForm()
    {
        return view('vehicles.import', ['columns' => self::IMPORT_COLUMNS]);
    }

    public function importTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::IMPORT_COLUMNS, ',', '"', '');
            fclose($out);
        }, 'vehicle-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle, 0, ',', '"', '') ?: []);
        if (! in_array('vin', $header, true)) {
            return back()->withErrors(['file' => 'The CSV must contain a header row with at least: vin, make, model, year.']);
        }

        $enums = config('autoshop.enums');
        $created = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($data, array_flip(self::IMPORT_COLUMNS)));
            $data['vin'] = Vin::normalize($data['vin'] ?? '');
            $data['body_type'] = $data['body_type'] ?: 'Other';
            $data['ownership_type'] = $data['ownership_type'] ?: 'company';
            $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

            $v = Validator::make($data, [
                'vin' => ['required', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/', 'unique:vehicles,vin'],
                'stock_number' => ['nullable', 'max:30', 'unique:vehicles,stock_number'],
                'make' => ['required', 'max:60'], 'model' => ['required', 'max:60'],
                'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 2)],
                'body_type' => [Rule::in($enums['body_types'])],
                'ownership_type' => [Rule::in(array_keys($enums['ownership_types']))],
                'condition' => ['nullable', Rule::in(array_keys($enums['conditions']))],
                'purchase_source' => ['nullable', Rule::in(array_keys($enums['purchase_sources']))],
                'mileage' => ['nullable', 'integer', 'min:0'], 'purchase_date' => ['nullable', 'date'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'], 'asking_price' => ['nullable', 'numeric', 'min:0'],
            ]);
            if ($v->fails()) {
                $errors[] = "Row {$line}: ".implode(' ', $v->errors()->all());

                continue;
            }
            DB::transaction(function () use ($data, $request, &$created) {
                $vehicle = new Vehicle(collect($data)->except(['stock_number', 'purchase_price'])->all());
                $vehicle->stock_number = ($data['stock_number'] ?? null) ?: NumberGenerator::next('stock', settings('general.stock_prefix', 'STK-'), 5);
                $vehicle->purchase_price = $data['purchase_price'] ?? 0;
                $vehicle->created_by = $request->user()->id;
                $vehicle->save();
                $this->costs->recalculate($vehicle);
                $created++;
            });
        }
        fclose($handle);
        AuditLogger::log('imported', null, "Vehicle import: {$created} created, ".count($errors).' rejected');

        return back()->with('success', "{$created} vehicle(s) imported.")->with('import_errors', $errors);
    }
}
