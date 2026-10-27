<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VinController;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\VehicleCostService;
use App\Services\VinDecoderService;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleApiController extends Controller
{
    public function index(Request $request)
    {
        $q = Vehicle::query()->with('primaryImage')
            ->when($request->q, fn ($x) => $x->where(fn ($w) => $w->where('vin', 'like', "%{$request->q}%")->orWhere('stock_number', 'like', "%{$request->q}%")
                ->orWhere('make', 'like', "%{$request->q}%")->orWhere('model', 'like', "%{$request->q}%")))
            ->when($request->inventory_status, fn ($x) => $x->where('inventory_status', $request->inventory_status))
            ->when($request->repair_status, fn ($x) => $x->where('repair_status', $request->repair_status))
            ->when($request->make, fn ($x) => $x->where('make', $request->make))
            ->when($request->year, fn ($x) => $x->where('year', $request->year))
            ->latest();

        return VehicleResource::collection($q->paginate(min(100, max(1, (int) $request->input('per_page', 25)))));
    }

    public function show(Vehicle $vehicle)
    {
        return new VehicleResource($vehicle);
    }

    public function store(VehicleRequest $request, VehicleCostService $costs)
    {
        $data = $request->validated();
        $vehicle = DB::transaction(function () use ($data, $costs, $request) {
            $v = new Vehicle(collect($data)->except(['images', 'documents', 'stock_number', 'decoded_data', 'purchase_price'])->all());
            $v->stock_number = $data['stock_number'] ?? null ?: NumberGenerator::next('stock', settings('general.stock_prefix', 'STK-'), 5);
            $v->purchase_price = $data['purchase_price'] ?? 0;
            $v->repair_status = $data['repair_status'] ?? 'not_started';
            $v->inventory_status = $data['inventory_status'] ?? 'available';
            $v->created_by = $request->user()->id;
            $v->save();

            return $costs->recalculate($v);
        });

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle, VehicleCostService $costs)
    {
        $data = $request->validated();
        DB::transaction(function () use ($vehicle, $data, $costs) {
            $repair = $data['repair_status'] ?? $vehicle->repair_status;
            $vehicle->fill(collect($data)->except(['images', 'documents', 'stock_number', 'decoded_data', 'repair_status', 'inventory_status'])->all())->save();
            $costs->changeStatus($vehicle, 'repair_status', $repair, 'Updated via API');
            $costs->recalculate($vehicle);
        });

        return new VehicleResource($vehicle->fresh());
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_if($vehicle->invoices()->where('status', '!=', 'void')->exists(), 422, 'Vehicle has invoices.');
        $vehicle->delete();

        return response()->noContent();
    }

    public function decodeVin(Request $request, VinDecoderService $decoder)
    {
        return app(VinController::class)->decode($request, $decoder);
    }
}
