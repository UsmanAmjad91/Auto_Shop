<?php

namespace App\Http\Requests;

use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('part') ? 'parts.edit' : 'parts.create');
    }

    public function rules(): array
    {
        $id = $this->route('part')?->id;

        return [
            'part_number' => ['nullable', 'string', 'max:30', Rule::unique('parts', 'part_number')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'oem_number' => ['nullable', 'string', 'max:60'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('parts', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'exists:part_categories,id'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'min_stock_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit' => ['required', 'string', 'max:20'],
            'default_location_id' => ['nullable', 'exists:stock_locations,id'],
            'shelf' => ['nullable', 'string', 'max:30'],
            'bin' => ['nullable', 'string', 'max:30'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_invoice' => ['nullable', 'string', 'max:100'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'warranty_terms' => ['nullable', 'string', 'max:2000'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'condition' => ['required', Rule::in(array_keys(config('autoshop.enums.part_conditions')))],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'initial_quantity' => [$id ? 'prohibited' : 'nullable', 'integer', 'min:0', 'max:1000000'],
            'compat' => ['nullable', 'array', 'max:50'],
            'compat.*.make' => ['required_with:compat.*.model', 'nullable', 'string', 'max:60'],
            'compat.*.model' => ['nullable', 'string', 'max:60'],
            'compat.*.year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'compat.*.year_to' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:compat.*.year_from'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => Uploads::imageRule(),
        ];
    }
}
