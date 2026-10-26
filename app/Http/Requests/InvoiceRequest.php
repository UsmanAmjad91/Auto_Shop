<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('invoice') ? 'invoices.edit' : 'invoices.create');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(config('autoshop.enums.invoice_types')))],
            'customer_id' => ['required', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'sales_rep_id' => ['nullable', 'exists:users,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type' => ['required', Rule::in(array_keys(config('autoshop.enums.invoice_item_types')))],
            'items.*.vehicle_id' => ['required_if:items.*.item_type,vehicle', 'nullable', 'exists:vehicles,id'],
            'items.*.part_id' => ['required_if:items.*.item_type,part', 'nullable', 'exists:parts,id'],
            'items.*.service_job_id' => ['required_if:items.*.item_type,service_job', 'nullable', 'exists:service_jobs,id'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items.*.taxable' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $v->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
            }
        }];
    }

    /** @return array{0: array, 1: array} header data and line items */
    public function invoiceData(): array
    {
        $data = $this->validated();
        $items = array_values($data['items']);
        unset($data['items']);

        return [$data, $items];
    }
}
