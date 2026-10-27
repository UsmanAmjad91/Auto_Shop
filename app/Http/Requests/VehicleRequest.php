<?php

namespace App\Http\Requests;

use App\Models\CustomField;
use App\Models\VehicleStatus;
use App\Support\Uploads;
use App\Support\Vin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('vehicle') ? 'vehicles.edit' : 'vehicles.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vin' => Vin::normalize($this->vin),
            'stock_number' => $this->stock_number ? strtoupper(trim($this->stock_number)) : null,
            'license_plate' => $this->license_plate ? strtoupper(trim($this->license_plate)) : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('vehicle')?->id;
        $enums = config('autoshop.enums');

        return [
            'vin' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/', Rule::unique('vehicles', 'vin')->ignore($id)],
            'stock_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/]+$/', Rule::unique('vehicles', 'stock_number')->ignore($id)],
            'make' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 2)],
            'trim' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:120'],
            'transmission' => ['nullable', 'string', 'max:60'],
            'fuel_type' => ['nullable', 'string', 'max:30'],
            'body_type' => ['required', Rule::in($enums['body_types'])],
            'drive_type' => ['nullable', 'string', 'max:30'],
            'exterior_color' => ['nullable', 'string', 'max:40'],
            'interior_color' => ['nullable', 'string', 'max:40'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'license_plate' => ['nullable', 'string', 'max:20'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_expiry' => ['nullable', 'date'],
            'registration_state' => ['nullable', 'string', 'max:60'],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'purchase_source' => ['nullable', Rule::in(array_keys($enums['purchase_sources']))],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'customer_id' => [Rule::requiredIf(fn () => $this->ownership_type === 'customer'), 'nullable', 'exists:customers,id'],
            'ownership_type' => ['required', Rule::in(array_keys($enums['ownership_types']))],
            'condition' => ['nullable', Rule::in(array_keys($enums['conditions']))],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'repair_status' => ['nullable', Rule::in(array_keys(VehicleStatus::options()))],
            'inventory_status' => ['nullable', Rule::in(array_keys($enums['inventory_statuses']))],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'asking_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => Uploads::imageRule(),
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => Uploads::documentRule(),
            'decoded_data' => ['nullable', 'json'],
        ] + CustomField::rulesFor('vehicles');
    }

    public function messages(): array
    {
        return [
            'vin.regex' => 'The VIN must be 17 characters (letters and digits, excluding I, O and Q).',
            'vin.unique' => 'A vehicle with this VIN already exists (it may be archived/deleted).',
            'customer_id.required' => 'Select the owner for customer vehicles.',
        ];
    }
}
