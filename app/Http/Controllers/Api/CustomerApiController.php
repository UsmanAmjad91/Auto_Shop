<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        return Customer::query()
            ->when($request->q, fn ($x) => $x->where(fn ($w) => $w->where('first_name', 'like', "%{$request->q}%")->orWhere('last_name', 'like', "%{$request->q}%")
                ->orWhere('company_name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")->orWhere('phone', 'like', "%{$request->q}%")))
            ->orderBy('first_name')->paginate(min(100, max(1, (int) $request->input('per_page', 25))))
            ->through(fn ($c) => $c->only(['id', 'code', 'type', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'city', 'is_active']) + ['display_name' => $c->display_name]);
    }

    public function show(Request $request, Customer $customer)
    {
        return $customer->only(['id', 'code', 'type', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country'])
            + ['display_name' => $customer->display_name,
                'outstanding_balance' => $request->user()->can('invoices.view') ? $customer->outstandingBalance() : null,
                'vehicles' => $customer->vehicles()->get(['id', 'vin', 'stock_number', 'year', 'make', 'model'])];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:individual,business'], 'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:100'],
        ]);
        $customer = new Customer($data + ['type' => 'individual', 'is_active' => true]);
        $customer->code = NumberGenerator::next('customer', settings('general.customer_prefix', 'CUS-'), 5);
        $customer->created_by = $request->user()->id;
        $customer->save();

        return response()->json($customer->only(['id', 'code', 'first_name', 'last_name', 'email', 'phone']), 201);
    }
}
