<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Part;
use App\Models\ServiceJob;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Remote data for searchable dropdowns (Tom Select). Each type checks its own permission. */
class LookupController extends Controller
{
    public function search(Request $request, string $type)
    {
        $term = trim((string) $request->input('q', ''));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
        $user = $request->user();
        $limit = 20;

        $results = match ($type) {
            'customers' => $this->guard($user, ['customers.view', 'invoices.create', 'payments.create', 'service_jobs.create', 'vehicles.create', 'vehicles.edit'],
                fn () => Customer::where('is_active', true)->when($term, fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)->orWhere('company_name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)->orWhere('code', 'like', $like)))
                    ->limit($limit)->get()->map(fn ($c) => ['id' => $c->id, 'text' => $c->display_name, 'sub' => trim($c->phone.' '.$c->email)])),
            'suppliers' => $this->guard($user, ['suppliers.view', 'parts.create', 'parts.edit', 'vehicles.create', 'vehicles.edit', 'purchase_orders.create'],
                fn () => Supplier::where('is_active', true)->when($term, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                    ->limit($limit)->get()->map(fn ($s) => ['id' => $s->id, 'text' => $s->name, 'sub' => $s->contact_person])),
            'vehicles' => $this->guard($user, ['vehicles.view'],
                fn () => Vehicle::when($request->boolean('for_sale'), fn ($q) => $q->whereIn('ownership_type', ['company', 'consignment'])->whereNull('sale_invoice_id'))
                    ->when($request->customer_id, fn ($q) => $q->where(fn ($w) => $w->where('customer_id', $request->customer_id)->orWhere('sold_to_customer_id', $request->customer_id)))
                    ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('vin', 'like', $like)->orWhere('stock_number', 'like', $like)
                        ->orWhere('make', 'like', $like)->orWhere('model', 'like', $like)->orWhere('license_plate', 'like', $like)))
                    ->limit($limit)->get()->map(fn ($v) => ['id' => $v->id, 'text' => $v->title(), 'sub' => "{$v->stock_number} · {$v->vin}",
                        'price' => $user->can('vehicles.view_costs') || $user->can('invoices.create') ? (float) $v->asking_price : null,
                        'customer_id' => $v->customer_id])),
            'parts' => $this->guard($user, ['parts.view'],
                fn () => Part::when($term, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('part_number', 'like', $like)
                    ->orWhere('oem_number', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like)))
                    ->limit($limit)->get()->map(fn ($p) => ['id' => $p->id, 'text' => $p->name, 'sub' => "{$p->part_number} · {$p->quantity_available} available",
                        'price' => (float) $p->selling_price, 'cost' => $user->can('financials.view') || $user->can('parts.edit') ? (float) $p->purchase_cost : null,
                        'available' => $p->quantity_available])),
            'employees' => $this->guard($user, ['employees.view', 'service_jobs.create', 'service_jobs.edit', 'tasks.create', 'vehicles.edit', 'vehicles.create', 'attendance.edit', 'parts.edit'],
                fn () => Employee::where('employment_status', '!=', 'terminated')->when($term, fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)->orWhere('employee_code', 'like', $like)))
                    ->limit($limit)->get()->map(fn ($e) => ['id' => $e->id, 'text' => $e->full_name, 'sub' => $e->job_title])),
            'service_jobs' => $this->guard($user, ['service_jobs.view'],
                fn () => ServiceJob::visibleTo($user)->when($request->boolean('billable'), fn ($q) => $q->whereNull('invoice_id')->where('status', '!=', 'cancelled'))
                    ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
                    ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('job_number', 'like', $like)->orWhere('title', 'like', $like)))
                    ->with('vehicle')->latest()->limit($limit)->get()->map(fn ($j) => ['id' => $j->id, 'text' => "{$j->job_number} — {$j->title}",
                        'sub' => $j->categoryLabel().' · '.$j->vehicle?->title(), 'price' => (float) $j->customer_charge])),
            'users' => $this->guard($user, ['users.view', 'invoices.create'],
                fn () => User::where('status', 'approved')->when($term, fn ($q) => $q->where('name', 'like', $like))->limit($limit)->get()
                    ->map(fn ($u) => ['id' => $u->id, 'text' => $u->name, 'sub' => $u->email])),
            default => abort(404),
        };

        return response()->json(['results' => $results->values()]);
    }

    protected function guard(User $user, array $permissions, \Closure $fn)
    {
        foreach ($permissions as $p) {
            if ($user->can($p)) {
                return $fn();
            }
        }
        abort(403);
    }
}
