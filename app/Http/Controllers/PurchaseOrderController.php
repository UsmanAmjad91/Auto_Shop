<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\SupplierPayment;
use App\Services\InventoryService;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\Money;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('purchase-orders.index');
        }

        return DataTable::make(PurchaseOrder::with('supplier'), $request)
            ->searchable(['po_number', 'supplier_invoice_number', 'supplier.name'])
            ->sortable(['order_date' => 'order_date', 'po_number' => 'po_number', 'total' => 'total', 'balance' => 'balance'], 'order_date')
            ->filters(fn ($q, $r) => $q->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->payment_status, fn ($x) => $x->where('payment_status', $r->payment_status))
                ->when($r->supplier_id, fn ($x) => $x->where('supplier_id', $r->supplier_id))
                ->when($r->date_from, fn ($x) => $x->whereDate('order_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('order_date', '<=', $r->date_to)))
            ->transform(fn (PurchaseOrder $p) => [
                'id' => $p->id, 'url' => route('purchase-orders.show', $p), 'po_number' => $p->po_number, 'supplier' => $p->supplier?->name,
                'order_date' => fmt_date($p->order_date), 'expected_date' => fmt_date($p->expected_date),
                'status' => ['label' => status_label($p->status), 'color' => status_color($p->status)],
                'payment_status' => ['label' => status_label($p->payment_status), 'color' => status_color($p->payment_status)],
                'total' => (float) $p->total, 'paid' => (float) $p->amount_paid, 'balance' => (float) $p->balance,
                'actions' => [['label' => 'View', 'icon' => 'eye', 'url' => route('purchase-orders.show', $p)]],
            ])->response();
    }

    public function create(Request $request)
    {
        $po = new PurchaseOrder(['order_date' => today(), 'location_id' => StockLocation::default()->id, 'supplier_id' => $request->supplier_id]);
        $prefill = [];
        if ($request->boolean('low_stock')) {
            $prefill = Part::lowStock()->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))->get()
                ->map(fn ($p) => ['part_id' => $p->id, 'label' => $p->name.' ('.$p->part_number.')',
                    'quantity_ordered' => max(1, $p->min_stock_level * 2 - $p->quantity_available), 'unit_cost' => (float) $p->purchase_cost])->all();
        }

        return view('purchase-orders.form', ['po' => $po, 'items' => $prefill, 'locations' => StockLocation::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $po = DB::transaction(function () use ($data, $request) {
            $po = new PurchaseOrder($data);
            $po->po_number = NumberGenerator::next('purchase_order', settings('general.po_prefix', 'PO-'));
            $po->status = 'draft';
            $po->created_by = $request->user()->id;
            $po->save();
            $this->syncItems($po, $data['items']);

            return $po;
        });

        return redirect()->route('purchase-orders.show', $po)->with('success', "Purchase order {$po->po_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return view('purchase-orders.show', ['po' => $purchaseOrder->load(['supplier', 'location', 'items.part', 'payments'])]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless(in_array($purchaseOrder->status, ['draft', 'ordered'], true), 403, 'Only draft or ordered POs can be edited.');
        $items = $purchaseOrder->items->load('part')->map(fn ($i) => ['part_id' => $i->part_id, 'label' => $i->part->name.' ('.$i->part->part_number.')',
            'quantity_ordered' => $i->quantity_ordered, 'unit_cost' => (float) $i->unit_cost])->all();

        return view('purchase-orders.form', ['po' => $purchaseOrder, 'items' => $items, 'locations' => StockLocation::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(in_array($purchaseOrder->status, ['draft', 'ordered'], true), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update($data);
            $this->syncItems($purchaseOrder, $data['items']);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 403, 'Only draft purchase orders can be deleted.');
        $purchaseOrder->delete();

        return $this->done($request, 'Purchase order deleted.', route('purchase-orders.index'));
    }

    public function markOrdered(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 422);
        $purchaseOrder->update(['status' => 'ordered']);

        return back()->with('success', 'Marked as ordered.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        abort_if($purchaseOrder->items()->where('quantity_received', '>', 0)->exists(), 422, 'Items have already been received.');
        abort_if($purchaseOrder->payments()->exists(), 422, 'Payments exist for this PO.');
        $purchaseOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Purchase order cancelled.');
    }

    /** Receive items into stock (atomic, supports partial receipts). */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, InventoryService $inventory)
    {
        abort_unless(in_array($purchaseOrder->status, ['ordered', 'partially_received', 'draft'], true), 422, 'This PO cannot receive stock.');
        $data = $request->validate(['receive' => ['required', 'array'], 'receive.*' => ['nullable', 'integer', 'min:0'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'], 'received_date' => ['nullable', 'date']]);

        DB::transaction(function () use ($purchaseOrder, $data, $inventory) {
            $any = false;
            foreach ($purchaseOrder->items()->lockForUpdate()->get() as $item) {
                $qty = (int) ($data['receive'][$item->id] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $remaining = $item->quantity_ordered - $item->quantity_received;
                if ($qty > $remaining) {
                    throw ValidationException::withMessages(['receive' => "Cannot receive {$qty} of {$item->part->name}; only {$remaining} outstanding."]);
                }
                $inventory->add($item->part, $qty, ['type' => 'purchase', 'location_id' => $purchaseOrder->location_id,
                    'unit_cost' => $item->unit_cost, 'reference' => $purchaseOrder, 'notes' => "Received on {$purchaseOrder->po_number}"]);
                $item->increment('quantity_received', $qty);
                $item->part->forceFill(['purchase_cost' => $item->unit_cost, 'purchase_date' => $data['received_date'] ?? today(),
                    'purchase_invoice' => $data['supplier_invoice_number'] ?? $item->part->purchase_invoice,
                    'supplier_id' => $item->part->supplier_id ?? $purchaseOrder->supplier_id])->saveQuietly();
                $any = true;
            }
            if (! $any) {
                throw ValidationException::withMessages(['receive' => 'Enter at least one quantity to receive.']);
            }
            $complete = $purchaseOrder->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
            $purchaseOrder->update([
                'status' => $complete ? 'received' : 'partially_received',
                'received_date' => $complete ? ($data['received_date'] ?? today()) : null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? $purchaseOrder->supplier_invoice_number,
            ]);
        });

        return back()->with('success', 'Stock received.');
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:'.implode(',', array_keys(config('autoshop.enums.payment_methods')))], 'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500']]);
        abort_if($purchaseOrder->status === 'cancelled', 422);
        if (Money::toCents($data['amount']) > Money::toCents($purchaseOrder->balance)) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the PO balance of '.money($purchaseOrder->balance).'.']);
        }
        DB::transaction(function () use ($purchaseOrder, $data, $request) {
            SupplierPayment::create($data + ['supplier_id' => $purchaseOrder->supplier_id, 'purchase_order_id' => $purchaseOrder->id, 'created_by' => $request->user()->id]);
            $this->refreshTotals($purchaseOrder);
        });

        return back()->with('success', 'Supplier payment recorded.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'location_id' => ['required', 'exists:stock_locations,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'due_date' => ['nullable', 'date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'exists:parts,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
    }

    protected function syncItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();
        foreach ($items as $i) {
            $po->items()->create($i + ['line_total' => Money::mul($i['unit_cost'], $i['quantity_ordered'])]);
        }
        $this->refreshTotals($po);
    }

    protected function refreshTotals(PurchaseOrder $po): void
    {
        $subtotal = Money::sum($po->items()->pluck('line_total'));
        $total = Money::add($subtotal, $po->tax_amount, $po->shipping_cost);
        $paid = Money::sum($po->payments()->pluck('amount'));
        $balance = Money::sub($total, $paid);
        $po->forceFill(['subtotal' => $subtotal, 'total' => $total, 'amount_paid' => $paid, 'balance' => $balance,
            'payment_status' => $balance <= 0 && $total > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid')])->save();
    }

    public function export(Request $request)
    {
        $rows = PurchaseOrder::with('supplier')->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest('order_date')->limit(20000)->get()
            ->map(fn ($p) => ['po' => $p->po_number, 'supplier' => $p->supplier?->name, 'date' => $p->order_date->toDateString(), 'status' => $p->status,
                'total' => $p->total, 'paid' => $p->amount_paid, 'balance' => $p->balance, 'payment' => $p->payment_status]);

        return Exporter::download($request->input('format', 'csv'), 'Purchase Orders', ['po' => 'PO #', 'supplier' => 'Supplier', 'date' => 'Order date',
            'status' => 'Status', 'total' => 'Total', 'paid' => 'Paid', 'balance' => 'Balance', 'payment' => 'Payment status'], $rows);
    }
}
