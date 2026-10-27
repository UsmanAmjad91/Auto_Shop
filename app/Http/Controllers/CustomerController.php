<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePdf;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\Money;
use App\Support\NumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerController extends Controller
{
    protected function table(Request $request): DataTable
    {
        return DataTable::make(Customer::query()->withCount(['vehicles', 'invoices']), $request)
            ->searchable(['code', 'first_name', 'last_name', 'company_name', 'email', 'phone', 'city'])
            ->sortable(['name' => 'first_name', 'code' => 'code', 'created_at' => 'created_at', 'city' => 'city'], 'created_at')
            ->filters(fn ($q, $r) => $q->when($r->type, fn ($x) => $x->where('type', $r->type))
                ->when($r->filled('is_active'), fn ($x) => $x->where('is_active', $r->boolean('is_active')))
                ->when($r->boolean('with_balance'), fn ($x) => $x->whereHas('invoices', fn ($i) => $i->open()))
                ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('customers.index');
        }
        $user = $request->user();
        $fin = $user->can('invoices.view') || $user->can('financials.view');

        return $this->table($request)->transform(fn (Customer $c) => [
            'id' => $c->id, 'url' => route('customers.show', $c), 'code' => $c->code, 'name' => $c->display_name, 'type' => ucfirst($c->type),
            'phone' => $c->phone, 'email' => $c->email, 'city' => $c->city, 'vehicles' => $c->vehicles_count, 'invoices' => $c->invoices_count,
            'balance' => $fin ? $c->outstandingBalance() : null, 'created_at' => fmt_date($c->created_at),
            'status' => ['label' => $c->is_active ? 'Active' : 'Inactive', 'color' => $c->is_active ? 'success' : 'secondary'],
            'actions' => array_values(array_filter([
                ['label' => 'View', 'icon' => 'eye', 'url' => route('customers.show', $c)],
                $user->can('customers.edit') ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('customers.edit', $c)] : null,
                $user->can('invoices.create') ? ['label' => 'New invoice', 'icon' => 'receipt', 'url' => route('invoices.create', ['customer_id' => $c->id])] : null,
                $user->can('customers.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('customers.destroy', $c), 'method' => 'DELETE', 'confirm' => 'Delete customer?', 'danger' => true] : null,
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn (Customer $c) => ['code' => $c->code, 'name' => $c->display_name, 'type' => $c->type,
            'phone' => $c->phone, 'email' => $c->email, 'address' => $c->fullAddress(), 'balance' => $c->outstandingBalance(), 'created_at' => fmt_date($c->created_at)]);

        return Exporter::download($request->input('format', 'csv'), 'Customers', ['code' => 'Code', 'name' => 'Name', 'type' => 'Type', 'phone' => 'Phone',
            'email' => 'Email', 'address' => 'Address', 'balance' => 'Outstanding Balance', 'created_at' => 'Created'], $rows);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer(['type' => 'individual', 'is_active' => true])]);
    }

    public function store(CustomerRequest $request)
    {
        $customer = $this->persist(new Customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    /** Modal "quick add" used by searchable selects in other modules. */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'],
        ]);
        $customer = $this->persist(new Customer, $data + ['type' => $data['company_name'] ?? null ? 'business' : 'individual', 'is_active' => true]);

        return response()->json(['id' => $customer->id, 'text' => $customer->display_name, 'sub' => trim($customer->phone.' '.$customer->email)]);
    }

    protected function persist(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->code ??= NumberGenerator::next('customer', settings('general.customer_prefix', 'CUS-'), 5);
        $customer->created_by ??= auth()->id();
        $customer->save();

        return $customer;
    }

    public function show(Request $request, Customer $customer)
    {
        $user = $request->user();

        return view('customers.show', [
            'customer' => $customer->load('attachments'),
            'vehicles' => $customer->vehicles()->latest()->get(),
            'purchased' => $customer->purchasedVehicles()->latest('sold_at')->get(),
            'jobs' => $user->can('service_jobs.view') ? $customer->serviceJobs()->visibleTo($user)->with('vehicle')->latest()->limit(50)->get() : collect(),
            'invoices' => $user->can('invoices.view') ? $customer->invoices()->latest('invoice_date')->limit(100)->get() : collect(),
            'payments' => $user->can('payments.view') ? $customer->payments()->latest('payment_date')->limit(100)->get() : collect(),
            'balance' => $customer->outstandingBalance(),
            'lender' => Money::sum(Invoice::where('customer_id', $customer->id)->open()->with('financing')->get()->map->pendingFinancedAmount()),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->persist($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        if ($customer->invoices()->whereNotIn('status', ['void'])->exists()) {
            $msg = 'Customers with invoices cannot be deleted. Mark them inactive instead.';

            return $request->expectsJson() ? response()->json(['message' => $msg], 422) : back()->withErrors(['customer' => $msg]);
        }
        $customer->delete();

        return $this->done($request, 'Customer deleted.', route('customers.index'));
    }

    /** Customer statement: opening balance, invoices & payments in a period, closing balance. */
    public function statement(Request $request, Customer $customer)
    {
        abort_unless($request->user()->can('invoices.view'), 403);
        $from = Carbon::parse($request->input('from', now()->subMonths(3)->startOfMonth()));
        $to = Carbon::parse($request->input('to', today()));

        $invoices = $customer->invoices()->whereNotIn('status', ['draft', 'void']);
        $payments = Payment::where('customer_id', $customer->id)->completed();

        $opening = Money::sub(
            Money::sum((clone $invoices)->whereDate('invoice_date', '<', $from)->pluck('total')),
            Money::sum((clone $payments)->whereDate('payment_date', '<', $from)->get()->map->signedAmount())
        );

        $lines = collect()
            ->merge((clone $invoices)->whereDateBetween('invoice_date', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn ($i) => ['date' => $i->invoice_date, 'ref' => $i->invoice_number, 'desc' => 'Invoice — '.config('autoshop.enums.invoice_types.'.$i->type), 'debit' => (float) $i->total, 'credit' => 0]))
            ->merge((clone $payments)->whereDateBetween('payment_date', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn ($p) => ['date' => $p->payment_date, 'ref' => $p->payment_number,
                    'desc' => ($p->isRefund() ? 'Refund' : config('autoshop.enums.payment_types.'.$p->type, 'Payment')).' — '.config('autoshop.enums.payment_methods.'.$p->method),
                    'debit' => $p->isRefund() ? (float) $p->amount : 0, 'credit' => $p->isRefund() ? 0 : (float) $p->amount]))
            ->sortBy(fn ($l) => $l['date']->format('Ymd').$l['ref'])->values();

        $running = $opening;
        $lines = $lines->map(function ($l) use (&$running) {
            $running = Money::add($running, $l['debit'], -$l['credit']);

            return $l + ['balance' => $running];
        });

        $data = ['customer' => $customer, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines, 'closing' => $running,
            'logo' => InvoicePdf::logoDataUri()];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('customers.statement-pdf', $data)->download("statement-{$customer->code}.pdf");
        }

        return view('customers.statement', $data + ['pdf' => false]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'action' => ['required', 'in:activate,deactivate,delete']]);
        abort_unless($request->user()->can($data['action'] === 'delete' ? 'customers.delete' : 'customers.edit'), 403);
        $n = 0;
        foreach (Customer::whereIn('id', $data['ids'])->get() as $c) {
            if ($data['action'] === 'delete') {
                if ($c->invoices()->where('status', '!=', 'void')->exists()) {
                    continue; // never delete customers with financial history
                }
                $c->delete();
            } else {
                $c->update(['is_active' => $data['action'] === 'activate']);
            }
            $n++;
        }

        return $this->done($request, "{$n} customer(s) updated.");
    }
}
