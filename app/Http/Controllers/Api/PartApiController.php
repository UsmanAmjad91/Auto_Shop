<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PartController;
use App\Models\Part;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class PartApiController extends Controller
{
    public function index(Request $request)
    {
        $costs = $request->user()->can('financials.view') || $request->user()->can('parts.edit');
        $q = Part::query()->with('category')
            ->when($request->q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('part_number', 'like', "%{$request->q}%")
                ->orWhere('oem_number', 'like', "%{$request->q}%")->orWhere('sku', 'like', "%{$request->q}%")->orWhere('barcode', $request->q)))
            ->when($request->stock_status, fn ($x) => $x->where('stock_status', $request->stock_status))
            ->orderBy('name');

        return $q->paginate(min(100, max(1, (int) $request->input('per_page', 25))))->through(fn (Part $p) => $this->present($p, $costs));
    }

    public function show(Request $request, Part $part)
    {
        return $this->present($part->load('category', 'stocks.location', 'compatibilities'), $request->user()->can('financials.view') || $request->user()->can('parts.edit'))
            + ['locations' => $part->stocks->map(fn ($s) => ['location' => $s->location?->name, 'quantity' => $s->quantity]),
                'compatibility' => $part->compatibilities->map->label()];
    }

    public function stock(Request $request, Part $part, InventoryService $inventory)
    {
        $request->headers->set('Accept', 'application/json');

        return app(PartController::class, ['inventory' => $inventory])->stock($request, $part);
    }

    protected function present(Part $p, bool $costs): array
    {
        return ['id' => $p->id, 'part_number' => $p->part_number, 'name' => $p->name, 'oem_number' => $p->oem_number, 'sku' => $p->sku,
            'barcode' => $p->barcode, 'category' => $p->category?->name, 'brand' => $p->brand, 'quantity' => $p->quantity,
            'reserved' => $p->quantity_reserved, 'available' => $p->quantity_available, 'min_stock_level' => $p->min_stock_level,
            'stock_status' => $p->stock_status, 'selling_price' => $p->selling_price] + ($costs ? ['purchase_cost' => $p->purchase_cost] : []);
    }
}
