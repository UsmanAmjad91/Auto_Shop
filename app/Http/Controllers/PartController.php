<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartRequest;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\PartImage;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Services\VehicleCostService;
use App\Support\Barcode;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\NumberGenerator;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PartController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    protected function table(Request $request): DataTable
    {
        return DataTable::make(Part::query()->with(['category', 'supplier', 'defaultLocation', 'primaryImage']), $request)
            ->searchable(['name', 'part_number', 'oem_number', 'sku', 'barcode', 'manufacturer', 'brand'])
            ->sortable(['name' => 'name', 'part_number' => 'part_number', 'quantity' => 'quantity', 'purchase_cost' => 'purchase_cost',
                'selling_price' => 'selling_price', 'created_at' => 'created_at', 'stock_status' => 'stock_status'], 'name', 'asc')
            ->filters(function ($q, Request $r) {
                $q->when($r->category_id, fn ($x) => $x->where('category_id', $r->category_id))
                    ->when($r->supplier_id, fn ($x) => $x->where('supplier_id', $r->supplier_id))
                    ->when($r->location_id, fn ($x) => $x->whereHas('stocks', fn ($s) => $s->where('location_id', $r->location_id)->where('quantity', '>', 0)))
                    ->when($r->condition, fn ($x) => $x->where('condition', $r->condition))
                    ->when($r->stock_status === 'low_stock', fn ($x) => $x->lowStock())
                    ->when($r->stock_status && $r->stock_status !== 'low_stock', fn ($x) => match ($r->stock_status) {
                        'reserved' => $x->where('quantity_reserved', '>', 0),
                        'damaged' => $x->where('quantity_damaged', '>', 0),
                        'returned' => $x->whereHas('movements', fn ($m) => $m->whereIn('type', ['return_to_supplier', 'job_return', 'sale_return'])),
                        default => $x->where('stock_status', $r->stock_status),
                    })
                    ->when($r->make, fn ($x) => $x->whereHas('compatibilities', fn ($c) => $c->where('make', $r->make)
                        ->when($r->model, fn ($m) => $m->where(fn ($w) => $w->whereNull('model')->orWhere('model', $r->model)))
                        ->when($r->year, fn ($y) => $y->where(fn ($w) => $w->whereNull('year_from')->orWhere('year_from', '<=', $r->year))
                            ->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $r->year)))))
                    ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                    ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to));
            });
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('parts.index', [
                'categories' => PartCategory::orderBy('name')->pluck('name', 'id'),
                'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
                'locations' => StockLocation::orderBy('name')->pluck('name', 'id'),
            ]);
        }
        $user = $request->user();
        $costs = $user->can('financials.view') || $user->can('parts.edit');

        return $this->table($request)->transform(fn (Part $p) => [
            'id' => $p->id, 'url' => route('parts.show', $p),
            'image' => $p->primaryImage ? asset('storage/'.$p->primaryImage->path) : null,
            'part_number' => $p->part_number, 'name' => $p->name, 'oem_number' => $p->oem_number, 'sku' => $p->sku,
            'category' => $p->category?->name, 'brand' => $p->brand, 'manufacturer' => $p->manufacturer,
            'quantity' => $p->quantity, 'reserved' => $p->quantity_reserved, 'available' => $p->quantity_available, 'min' => $p->min_stock_level,
            'purchase_cost' => $costs ? (float) $p->purchase_cost : null, 'selling_price' => (float) $p->selling_price,
            'value' => $costs ? $p->stockValue() : null,
            'location' => trim(($p->defaultLocation?->name ?? '').' '.($p->shelf ? "/ {$p->shelf}" : '').($p->bin ? " / {$p->bin}" : '')),
            'supplier' => $p->supplier?->name, 'condition' => ucfirst($p->condition),
            'stock_status' => ['label' => status_label($p->stock_status), 'color' => status_color($p->stock_status)],
            'actions' => array_values(array_filter([
                ['label' => 'View', 'icon' => 'eye', 'url' => route('parts.show', $p)],
                $user->can('parts.edit') ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('parts.edit', $p)] : null,
                $user->can('parts.adjust_stock') ? ['label' => 'Stock', 'icon' => 'arrow-left-right', 'url' => route('parts.show', $p).'#stock'] : null,
                $user->can('parts.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('parts.destroy', $p), 'method' => 'DELETE', 'confirm' => "Delete {$p->name}?", 'danger' => true] : null,
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $costs = $request->user()->can('financials.view') || $request->user()->can('parts.edit');
        $columns = ['part_number' => 'Part ID', 'name' => 'Name', 'oem_number' => 'OEM #', 'sku' => 'SKU', 'barcode' => 'Barcode', 'category' => 'Category',
            'brand' => 'Brand', 'manufacturer' => 'Manufacturer', 'condition' => 'Condition', 'quantity' => 'On Hand', 'reserved' => 'Reserved',
            'available' => 'Available', 'min' => 'Min Level', 'status' => 'Status']
            + ($costs ? ['purchase_cost' => 'Unit Cost', 'value' => 'Stock Value'] : []) + ['selling_price' => 'Price', 'location' => 'Location', 'supplier' => 'Supplier'];
        $rows = $this->table($request)->all()->map(fn (Part $p) => [
            'part_number' => $p->part_number, 'name' => $p->name, 'oem_number' => $p->oem_number, 'sku' => $p->sku, 'barcode' => $p->barcode,
            'category' => $p->category?->name, 'brand' => $p->brand, 'manufacturer' => $p->manufacturer, 'condition' => $p->condition,
            'quantity' => $p->quantity, 'reserved' => $p->quantity_reserved, 'available' => $p->quantity_available, 'min' => $p->min_stock_level,
            'status' => status_label($p->stock_status), 'purchase_cost' => $p->purchase_cost, 'value' => $p->stockValue(),
            'selling_price' => $p->selling_price, 'location' => trim($p->defaultLocation?->name.' '.$p->shelf.' '.$p->bin), 'supplier' => $p->supplier?->name,
        ]);

        return Exporter::download($request->input('format', 'csv'), 'Parts Inventory', $columns, $rows);
    }

    public function create()
    {
        return view('parts.form', $this->formData(new Part(['condition' => 'new', 'unit' => 'pcs',
            'min_stock_level' => (int) settings('inventory.low_stock_default', 5), 'default_location_id' => StockLocation::default()->id])));
    }

    public function store(PartRequest $request)
    {
        $data = $request->validated();
        $part = DB::transaction(function () use ($request, $data) {
            $part = new Part(collect($data)->except(['initial_quantity', 'compat', 'images', 'part_number'])->all());
            $part->part_number = $data['part_number'] ?: NumberGenerator::next('part', settings('general.part_prefix', 'PRT-'), 5);
            $part->allow_negative_stock = $request->boolean('allow_negative_stock') && $request->user()->can('parts.negative_stock');
            $part->stock_status = 'out_of_stock';
            $part->save();
            $this->syncCompat($part, $data['compat'] ?? []);
            $this->saveImages($request, $part);

            if (($qty = (int) ($data['initial_quantity'] ?? 0)) > 0) {
                $this->inventory->add($part, $qty, ['type' => 'add', 'location_id' => $part->default_location_id, 'notes' => 'Opening stock']);
            }

            return $part;
        });

        return redirect()->route('parts.show', $part)->with('success', 'Part created.');
    }

    public function show(Request $request, Part $part)
    {
        $part->load(['category', 'supplier', 'supervisor', 'defaultLocation', 'images', 'compatibilities', 'stocks.location', 'attachments']);

        return view('parts.show', [
            'part' => $part,
            'movements' => $part->movements()->with(['user', 'location', 'vehicle'])->limit(100)->get(),
            'locations' => StockLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'canCosts' => $request->user()->can('financials.view') || $request->user()->can('parts.edit'),
            'barcode' => Barcode::code39Svg($part->sku ?: $part->part_number, 40, 1.2),
        ]);
    }

    public function edit(Part $part)
    {
        return view('parts.form', $this->formData($part->load('compatibilities', 'images')));
    }

    public function update(PartRequest $request, Part $part)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $part, $data) {
            $part->fill(collect($data)->except(['initial_quantity', 'compat', 'images', 'part_number'])->all());
            if (! empty($data['part_number'])) {
                $part->part_number = $data['part_number'];
            }
            if ($request->user()->can('parts.negative_stock')) {
                $part->allow_negative_stock = $request->boolean('allow_negative_stock');
            }
            $part->stock_status = $part->computeStockStatus();
            $part->save();
            $this->syncCompat($part, $data['compat'] ?? []);
            $this->saveImages($request, $part);
        });

        return redirect()->route('parts.show', $part)->with('success', 'Part updated.');
    }

    public function destroy(Request $request, Part $part)
    {
        if ($part->quantity !== 0 || $part->quantity_reserved > 0) {
            $msg = 'Remove or adjust remaining stock to zero before deleting this part.';

            return $request->expectsJson() ? response()->json(['message' => $msg], 422) : back()->withErrors(['part' => $msg]);
        }
        $part->delete();

        return $this->done($request, 'Part deleted.', route('parts.index'));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', 'in:delete,category,supplier,min_level'], 'value' => ['nullable', 'string', 'max:60']]);
        abort_unless($request->user()->can($data['action'] === 'delete' ? 'parts.delete' : 'parts.edit'), 403);
        $n = 0;
        foreach (Part::whereIn('id', $data['ids'])->get() as $part) {
            match ($data['action']) {
                'delete' => $part->quantity === 0 && $part->quantity_reserved === 0 ? $part->delete() : null,
                'category' => $part->update(['category_id' => PartCategory::find($data['value'])?->id]),
                'supplier' => $part->update(['supplier_id' => Supplier::find($data['value'])?->id]),
                'min_level' => tap($part)->update(['min_stock_level' => max(0, (int) $data['value'])])
                    ->forceFill(['stock_status' => $part->computeStockStatus()])->saveQuietly(),
            };
            $n++;
        }

        return $this->done($request, "Bulk action applied to {$n} part(s).");
    }

    /** add | remove | adjust | reserve | unreserve | transfer | damaged | return_to_supplier | assign_vehicle */
    public function stock(Request $request, Part $part)
    {
        $data = $request->validate([
            'operation' => ['required', 'in:add,remove,adjust,reserve,unreserve,transfer,damaged,return_to_supplier,assign_vehicle'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'location_id' => ['nullable', 'exists:stock_locations,id'],
            'to_location_id' => ['required_if:operation,transfer', 'nullable', 'exists:stock_locations,id'],
            'vehicle_id' => ['required_if:operation,assign_vehicle', 'nullable', 'exists:vehicles,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $negative = $request->user()->can('parts.negative_stock') && $request->boolean('allow_negative');
        $loc = $data['location_id'] ?? $part->default_location_id ?? StockLocation::default()->id;
        $opts = ['location_id' => $loc, 'notes' => $data['notes'] ?? null, 'allow_negative' => $negative];
        $qty = (int) $data['quantity'];

        match ($data['operation']) {
            'add' => $this->inventory->add($part, $qty, $opts + ['unit_cost' => $data['unit_cost'] ?? null]),
            'remove' => $this->inventory->remove($part, $qty, $opts),
            'adjust' => $this->inventory->adjustTo($part, $qty, $loc, $data['notes'] ?? 'Stock count adjustment', $negative),
            'reserve' => $this->inventory->reserve($part, $qty, $opts),
            'unreserve' => $this->inventory->unreserve($part, $qty, $opts),
            'transfer' => $this->inventory->transfer($part, (int) $loc, (int) $data['to_location_id'], $qty, $data['notes'] ?? null),
            'damaged' => $this->inventory->remove($part, $qty, $opts + ['type' => 'damaged']),
            'return_to_supplier' => $this->inventory->remove($part, $qty, $opts + ['type' => 'return_to_supplier']),
            'assign_vehicle' => $this->inventory->remove($part, $qty, $opts + ['type' => 'job_issue', 'vehicle_id' => $data['vehicle_id'],
                'notes' => trim('Assigned to vehicle '.Vehicle::find($data['vehicle_id'])?->stock_number.'. '.($data['notes'] ?? ''))]),
        };

        // Direct vehicle assignment counts toward the vehicle's "other" costs via an expense record.
        if ($data['operation'] === 'assign_vehicle') {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $vehicle->expenses()->create(['category' => 'other', 'description' => "Part {$part->part_number} {$part->name} ×{$qty}",
                'amount' => round((float) $part->purchase_cost * $qty, 2), 'expense_date' => today(), 'created_by' => $request->user()->id]);
            app(VehicleCostService::class)->recalculate($vehicle);
        }

        return $this->done($request, 'Stock updated. On hand: '.$part->fresh()->quantity.'.');
    }

    public function movements(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('parts.movements', ['types' => StockMovement::TYPES, 'locations' => StockLocation::orderBy('name')->pluck('name', 'id')]);
        }

        return DataTable::make(StockMovement::with(['part', 'user', 'location', 'vehicle']), $request)
            ->searchable(['notes', 'part.name', 'part.part_number'])
            ->sortable(['created_at' => 'created_at', 'quantity' => 'quantity'], 'created_at')
            ->filters(fn ($q, $r) => $q->when($r->type, fn ($x) => $x->where('type', $r->type))
                ->when($r->part_id, fn ($x) => $x->where('part_id', $r->part_id))
                ->when($r->location_id, fn ($x) => $x->where('location_id', $r->location_id))
                ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to)))
            ->transform(fn (StockMovement $m) => [
                'id' => $m->id, 'date' => $m->created_at->format('Y-m-d H:i'), 'part' => $m->part?->name, 'part_number' => $m->part?->part_number,
                'url' => $m->part ? route('parts.show', $m->part_id) : null,
                'type' => ['label' => StockMovement::TYPES[$m->type] ?? $m->type, 'color' => $m->quantity > 0 ? 'success' : ($m->quantity < 0 ? 'danger' : 'secondary')],
                'quantity' => ($m->quantity > 0 ? '+' : '').$m->quantity, 'reserved' => $m->reserved_change ?: '', 'balance' => $m->balance_after,
                'location' => $m->location?->name, 'vehicle' => $m->vehicle?->stock_number, 'user' => $m->user?->name, 'notes' => $m->notes,
            ])->response();
    }

    public function destroyImage(Part $part, PartImage $image)
    {
        abort_unless($image->part_id === $part->id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function label(Part $part)
    {
        return view('parts.label', [
            'part' => $part,
            'barcode' => Barcode::code39Svg($part->sku ?: $part->part_number, 40, 1.1),
            'qr' => (string) QrCode::format('svg')->size(90)->margin(0)->generate(route('parts.show', $part)),
        ]);
    }

    protected function formData(Part $part): array
    {
        return [
            'part' => $part,
            'categories' => PartCategory::orderBy('name')->pluck('name', 'id'),
            'locations' => StockLocation::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function syncCompat(Part $part, array $rows): void
    {
        $part->compatibilities()->delete();
        foreach ($rows as $r) {
            if (! empty($r['make'])) {
                $part->compatibilities()->create(collect($r)->only(['make', 'model', 'year_from', 'year_to'])->all());
            }
        }
    }

    protected function saveImages(Request $request, Part $part): void
    {
        $hasPrimary = $part->images()->where('is_primary', true)->exists();
        foreach ((array) $request->file('images', []) as $i => $file) {
            $part->images()->create(['path' => Uploads::image($file, "parts/{$part->id}"), 'is_primary' => ! $hasPrimary && $i === 0]);
        }
    }

    /* ---------------- import ---------------- */

    public const IMPORT_COLUMNS = ['part_number', 'name', 'oem_number', 'sku', 'barcode', 'category', 'manufacturer', 'brand', 'purchase_cost',
        'selling_price', 'quantity', 'min_stock_level', 'unit', 'shelf', 'bin', 'supplier', 'condition', 'warranty_months', 'description'];

    public function importForm()
    {
        return view('parts.import', ['columns' => self::IMPORT_COLUMNS]);
    }

    public function importTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::IMPORT_COLUMNS, ',', '"', '');
            fclose($out);
        }, 'parts-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle, 0, ',', '"', '') ?: []);
        if (! in_array('name', $header, true)) {
            return back()->withErrors(['file' => 'The CSV must contain a header row including at least "name".']);
        }
        $location = StockLocation::default();
        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $d = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $d = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, array_intersect_key($d, array_flip(self::IMPORT_COLUMNS)));
            $d['condition'] ??= 'new';
            $v = Validator::make($d, [
                'name' => ['required', 'max:255'], 'purchase_cost' => ['nullable', 'numeric', 'min:0'], 'selling_price' => ['nullable', 'numeric', 'min:0'],
                'quantity' => ['nullable', 'integer', 'min:0'], 'min_stock_level' => ['nullable', 'integer', 'min:0'],
                'condition' => [Rule::in(['new', 'used', 'refurbished'])], 'warranty_months' => ['nullable', 'integer', 'min:0'],
            ]);
            if ($v->fails()) {
                $errors[] = "Row {$line}: ".implode(' ', $v->errors()->all());

                continue;
            }
            try {
                DB::transaction(function () use ($d, $location, &$created, &$updated) {
                    $existing = ($d['sku'] ?? null) ? Part::where('sku', $d['sku'])->first() : (($d['part_number'] ?? null) ? Part::where('part_number', $d['part_number'])->first() : null);
                    $part = $existing ?? new Part;
                    $part->fill([
                        'name' => $d['name'], 'oem_number' => $d['oem_number'] ?? null, 'sku' => $d['sku'] ?? null, 'barcode' => $d['barcode'] ?? null,
                        'category_id' => ($d['category'] ?? null) ? PartCategory::firstOrCreate(['name' => $d['category']])->id : $part->category_id,
                        'manufacturer' => $d['manufacturer'] ?? null, 'brand' => $d['brand'] ?? null,
                        'purchase_cost' => $d['purchase_cost'] ?? 0, 'selling_price' => $d['selling_price'] ?? 0,
                        'min_stock_level' => $d['min_stock_level'] ?? (int) settings('inventory.low_stock_default', 5), 'unit' => $d['unit'] ?? 'pcs',
                        'shelf' => $d['shelf'] ?? null, 'bin' => $d['bin'] ?? null, 'condition' => $d['condition'],
                        'supplier_id' => ($d['supplier'] ?? null) ? Supplier::where('name', $d['supplier'])->value('id') : $part->supplier_id,
                        'warranty_months' => $d['warranty_months'] ?? null, 'description' => $d['description'] ?? null,
                        'default_location_id' => $part->default_location_id ?? $location->id,
                    ]);
                    $part->part_number ??= $d['part_number'] ?? NumberGenerator::next('part', settings('general.part_prefix', 'PRT-'), 5);
                    $part->stock_status ??= 'out_of_stock';
                    $part->save();
                    if (! $existing && ($q = (int) ($d['quantity'] ?? 0)) > 0) {
                        $this->inventory->add($part, $q, ['location_id' => $part->default_location_id, 'notes' => 'Imported opening stock']);
                    }
                    $existing ? $updated++ : $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = "Row {$line}: ".$e->getMessage();
            }
        }
        fclose($handle);
        AuditLogger::log('imported', null, "Parts import: {$created} created, {$updated} updated, ".count($errors).' rejected');

        return back()->with('success', "{$created} part(s) created, {$updated} updated. Quantities of existing parts are only changed through stock operations.")
            ->with('import_errors', $errors);
    }
}
