<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $costs = $request->user()?->can('vehicles.view_costs');

        return [
            'id' => $this->id, 'vin' => $this->vin, 'stock_number' => $this->stock_number, 'year' => $this->year, 'make' => $this->make,
            'model' => $this->model, 'trim' => $this->trim, 'engine' => $this->engine, 'transmission' => $this->transmission,
            'fuel_type' => $this->fuel_type, 'body_type' => $this->body_type, 'exterior_color' => $this->exterior_color,
            'interior_color' => $this->interior_color, 'mileage' => $this->mileage, 'license_plate' => $this->license_plate,
            'ownership_type' => $this->ownership_type, 'condition' => $this->condition, 'location' => $this->location,
            'repair_status' => $this->repair_status, 'inventory_status' => $this->inventory_status, 'payment_status' => $this->payment_status,
            'asking_price' => $this->asking_price, 'sale_price' => $this->sale_price,
            'costs' => $this->when($costs, fn () => [
                'purchase_price' => $this->purchase_price, 'parts_cost' => $this->parts_cost, 'labor_cost' => $this->labor_cost,
                'interior_cost' => $this->interior_cost, 'detailing_cost' => $this->detailing_cost, 'other_cost' => $this->other_cost,
                'total_investment' => $this->total_investment, 'profit' => $this->profit(), 'profit_is_estimate' => $this->isProfitEstimated(),
            ]),
            'primary_image' => $this->primaryImageUrl(),
            'custom_fields' => $this->custom_fields,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
