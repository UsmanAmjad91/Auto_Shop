<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\ServiceJob;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Global search across every module the user is authorised to view. */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $user = $request->user();
        $results = [];

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $limit = $request->expectsJson() ? 5 : 25;

            if ($user->can('vehicles.view')) {
                $results['Vehicles'] = Vehicle::where(fn ($q) => $q->where('vin', 'like', $like)->orWhere('stock_number', 'like', $like)
                    ->orWhere('make', 'like', $like)->orWhere('model', 'like', $like)->orWhere('license_plate', 'like', $like))
                    ->limit($limit)->get()->map(fn ($v) => ['title' => $v->title(), 'subtitle' => "VIN {$v->vin} · {$v->stock_number}", 'url' => route('vehicles.show', $v), 'icon' => 'car-front']);
            }
            if ($user->can('customers.view')) {
                $results['Customers'] = Customer::where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('code', 'like', $like))
                    ->limit($limit)->get()->map(fn ($c) => ['title' => $c->display_name, 'subtitle' => trim("{$c->code} · {$c->phone} {$c->email}"), 'url' => route('customers.show', $c), 'icon' => 'person']);
            }
            if ($user->can('parts.view')) {
                $results['Parts'] = Part::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('part_number', 'like', $like)
                    ->orWhere('oem_number', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like))
                    ->limit($limit)->get()->map(fn ($p) => ['title' => $p->name, 'subtitle' => "{$p->part_number} · OEM {$p->oem_number} · {$p->quantity_available} available", 'url' => route('parts.show', $p), 'icon' => 'box-seam']);
            }
            if ($user->can('employees.view')) {
                $results['Employees'] = Employee::where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_code', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                    ->limit($limit)->get()->map(fn ($e) => ['title' => $e->full_name, 'subtitle' => "{$e->employee_code} · {$e->job_title}", 'url' => route('employees.show', $e), 'icon' => 'person-badge']);
            }
            if ($user->can('service_jobs.view')) {
                $results['Service Jobs'] = ServiceJob::visibleTo($user)->where(fn ($q) => $q->where('job_number', 'like', $like)->orWhere('title', 'like', $like)
                    ->orWhereHas('vehicle', fn ($v) => $v->where('vin', 'like', $like)))
                    ->with('vehicle')->limit($limit)->get()->map(fn ($j) => ['title' => "{$j->job_number} — {$j->title}", 'subtitle' => $j->categoryLabel().' · '.status_label($j->status).' · '.$j->vehicle?->title(), 'url' => route('service-jobs.show', $j), 'icon' => 'tools']);
            }
            if ($user->can('invoices.view')) {
                $results['Invoices'] = Invoice::where(fn ($q) => $q->where('invoice_number', 'like', $like)->orWhere('customer_name', 'like', $like))
                    ->limit($limit)->get()->map(fn ($i) => ['title' => $i->invoice_number, 'subtitle' => $i->customer_name.' · '.status_label($i->status).($user->can('financials.view') || $user->can('invoices.view') ? ' · '.money($i->total) : ''), 'url' => route('invoices.show', $i), 'icon' => 'receipt']);
            }
            if ($user->can('suppliers.view')) {
                $results['Suppliers'] = Supplier::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_person', 'like', $like)->orWhere('email', 'like', $like)->orWhere('code', 'like', $like))
                    ->limit($limit)->get()->map(fn ($s) => ['title' => $s->name, 'subtitle' => trim("{$s->code} · {$s->contact_person}"), 'url' => route('suppliers.show', $s), 'icon' => 'truck']);
            }
            $results = array_filter($results, fn ($r) => $r->isNotEmpty());
        }

        if ($request->expectsJson()) {
            return response()->json(['results' => $results]);
        }

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
