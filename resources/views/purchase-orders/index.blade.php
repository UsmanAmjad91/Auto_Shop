@extends('layouts.app')
@section('title', 'Purchase Orders')
@section('content')
<x-page-header title="Purchase Orders" :breadcrumbs="['Parts' => route('parts.index'), 'Purchase orders' => null]">
    @can('purchase_orders.create')<a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New PO</a>@endcan
</x-page-header>
<x-filters for="po-table">
    <x-form.select name="status" label="Status" :options="\App\Models\PurchaseOrder::STATUSES" placeholder="Any" col="col-6 col-md-3" />
    <x-form.select name="payment_status" label="Payment" :options="['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid']" placeholder="Any" col="col-6 col-md-2" />
    <x-form.remote name="supplier_id" type="suppliers" label="Supplier" col="col-6 col-md-3" />
    <div class="col-6 col-md-4"><label class="form-label">Order date</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="po-table" :url="route('purchase-orders.index')" module="purchase_orders" sort="order_date" :export-url="route('purchase-orders.export')" :columns="[
    ['key' => 'po_number', 'label' => 'PO #', 'type' => 'link', 'sortable' => true],
    ['key' => 'supplier', 'label' => 'Supplier'],
    ['key' => 'order_date', 'label' => 'Ordered', 'sortable' => true],
    ['key' => 'expected_date', 'label' => 'Expected'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'badge'],
    ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sortable' => true],
    ['key' => 'paid', 'label' => 'Paid', 'type' => 'money'],
    ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'sortable' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@endsection
