<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    protected function table(Request $request): DataTable
    {
        return DataTable::make(Supplier::query()->withCount('parts'), $request)
            ->searchable(['name', 'code', 'contact_person', 'email', 'phone', 'city'])
            ->sortable(['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'], 'name', 'asc')
            ->filters(fn ($q, $r) => $q->when($r->filled('is_active'), fn ($x) => $x->where('is_active', $r->boolean('is_active'))));
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('suppliers.index');
        }
        $user = $request->user();

        return $this->table($request)->transform(fn (Supplier $s) => [
            'id' => $s->id, 'url' => route('suppliers.show', $s), 'code' => $s->code, 'name' => $s->name, 'contact' => $s->contact_person,
            'phone' => $s->phone, 'email' => $s->email, 'city' => $s->city, 'parts' => $s->parts_count,
            'pending' => $user->can('financials.view') || $user->can('purchase_orders.view') ? $s->amountPending() : null,
            'status' => ['label' => $s->is_active ? 'Active' : 'Inactive', 'color' => $s->is_active ? 'success' : 'secondary'],
            'actions' => array_values(array_filter([
                ['label' => 'View', 'icon' => 'eye', 'url' => route('suppliers.show', $s)],
                $user->can('suppliers.edit') ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('suppliers.edit', $s)] : null,
                $user->can('suppliers.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('suppliers.destroy', $s), 'method' => 'DELETE', 'confirm' => 'Delete supplier?', 'danger' => true] : null,
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn ($s) => ['code' => $s->code, 'name' => $s->name, 'contact' => $s->contact_person, 'phone' => $s->phone,
            'email' => $s->email, 'address' => $s->address, 'city' => $s->city, 'country' => $s->country, 'website' => $s->website,
            'purchased' => $s->totalPurchased(), 'paid' => $s->totalPaid(), 'pending' => $s->amountPending()]);

        return Exporter::download($request->input('format', 'csv'), 'Suppliers', ['code' => 'Code', 'name' => 'Name', 'contact' => 'Contact', 'phone' => 'Phone',
            'email' => 'Email', 'address' => 'Address', 'city' => 'City', 'country' => 'Country', 'website' => 'Website', 'purchased' => 'Purchased',
            'paid' => 'Paid', 'pending' => 'Pending'], $rows);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'payment_terms_days' => 30])]);
    }

    public function store(SupplierRequest $request)
    {
        $supplier = new Supplier($request->validated());
        $supplier->code = NumberGenerator::next('supplier', settings('general.supplier_prefix', 'SUP-'), 4);
        $supplier->save();

        return $request->expectsJson()
            ? response()->json(['id' => $supplier->id, 'text' => $supplier->name])
            : redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier)
    {
        return view('suppliers.show', [
            'supplier' => $supplier->load('attachments'),
            'orders' => $supplier->purchaseOrders()->latest('order_date')->limit(50)->get(),
            'payments' => $supplier->payments()->with('purchaseOrder')->latest('payment_date')->limit(50)->get(),
            'parts' => $supplier->parts()->orderBy('name')->limit(100)->get(),
            'vehicles' => $supplier->vehicles()->latest()->limit(50)->get(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->whereNotIn('status', ['cancelled'])->where('balance', '>', 0)->exists()) {
            return back()->withErrors(['supplier' => 'This supplier has unpaid purchase orders.']);
        }
        $supplier->delete();

        return $this->done($request, 'Supplier deleted.', route('suppliers.index'));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'action' => ['required', 'in:activate,deactivate']]);
        abort_unless($request->user()->can('suppliers.edit'), 403);
        $n = Supplier::whereIn('id', $data['ids'])->get()->each(fn ($s) => $s->update(['is_active' => $data['action'] === 'activate']))->count();

        return $this->done($request, "{$n} supplier(s) updated.");
    }
}
