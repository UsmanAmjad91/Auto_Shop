@php
    $user = auth()->user();
    $themeMode = $user?->theme ?: settings('theme.mode', 'auto');
    $font = settings('theme.font', 'Inter');
    $logo = settings('general.logo');
    $favicon = settings('general.favicon');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-mode="{{ $themeMode }}" data-bs-theme="{{ $themeMode === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ settings('general.company_name') }}</title>
    @if($favicon)<link rel="icon" href="{{ asset('storage/'.$favicon) }}">@endif
    @if($font !== 'system-ui')
        <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ urlencode($font) }}:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif
    <script>
        (function () { var m = document.documentElement.dataset.themeMode;
            if (m === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.setAttribute('data-bs-theme', 'dark'); })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --as-primary: {{ settings('theme.primary_color', '#1f6feb') }};
            --as-secondary: {{ settings('theme.secondary_color', '#6c757d') }};
            --as-sidebar: {{ settings('theme.sidebar_color', '#111827') }};
            --as-font: "{{ $font }}", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --as-logo-h: {{ (int) settings('theme.logo_height', 34) }}px;
        }
        :root:not([data-bs-theme="dark"]) { --as-header: {{ settings('theme.header_color', '#ffffff') }}; }
    </style>
    @stack('head')
</head>
<body class="{{ settings()->bool('theme.sidebar_collapsed') ? 'sidebar-collapsed' : '' }}" data-currency="{{ settings('general.currency_symbol', '$') }}" data-per-page="{{ settings('general.pagination', 25) }}">
<a href="#main" class="visually-hidden-focusable position-absolute p-2 bg-body">Skip to content</a>
<div class="app-wrapper">
    @include('partials.sidebar')
    <div class="sidebar-backdrop"></div>
    <div class="app-main">
        @include('partials.topbar')
        <main class="app-content" id="main">
            @include('partials.flash')
            @yield('content')
        </main>
        <footer class="app-footer d-flex justify-content-between">
            <span>&copy; {{ date('Y') }} {{ settings('general.company_name') }}</span>
            <span class="d-none d-sm-inline">AutoShop Manager</span>
        </footer>
    </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts"></div>

@can('customers.create')
    <x-modal id="quickCustomerModal" title="Quick add customer">
        <form method="POST" action="{{ route('customers.quick') }}" data-ajax data-reset>
            @csrf
            <div class="row g-2">
                <x-form.input name="first_name" label="First name" required col="col-6" />
                <x-form.input name="last_name" label="Last name" col="col-6" />
                <x-form.input name="company_name" label="Company (optional)" col="col-12" />
                <x-form.input name="phone" label="Phone" col="col-6" />
                <x-form.input name="email" label="Email" type="email" col="col-6" />
            </div>
            <div class="text-end mt-3"><button class="btn btn-primary">Save customer</button></div>
        </form>
    </x-modal>
@endcan
@can('suppliers.create')
    <x-modal id="quickSupplierModal" title="Quick add supplier">
        <form method="POST" action="{{ route('suppliers.store') }}" data-ajax data-reset data-redirect="false">
            @csrf
            <input type="hidden" name="is_active" value="1">
            <div class="row g-2">
                <x-form.input name="name" label="Supplier name" required col="col-12" id="qs-name" />
                <x-form.input name="contact_person" label="Contact person" col="col-6" id="qs-contact" />
                <x-form.input name="phone" label="Phone" col="col-6" id="qs-phone" />
                <x-form.input name="email" label="Email" type="email" col="col-12" id="qs-email" />
            </div>
            <div class="form-text">More details can be added later under Suppliers.</div>
            <div class="text-end mt-3"><button class="btn btn-primary">Save supplier</button></div>
        </form>
    </x-modal>
@endcan
@can('parts.create')
    <x-modal id="quickPartModal" title="Quick add part">
        <form method="POST" action="{{ route('parts.store') }}" data-ajax data-reset data-redirect="false">
            @csrf
            <input type="hidden" name="unit" value="pcs">
            <input type="hidden" name="condition" value="new">
            <input type="hidden" name="min_stock_level" value="{{ (int) settings('inventory.low_stock_default', 5) }}">
            <input type="hidden" name="default_location_id" value="{{ \App\Models\StockLocation::where('is_default', true)->value('id') }}">
            <div class="row g-2">
                <x-form.input name="name" label="Part name" required col="col-12" id="qp-name" />
                <x-form.input name="oem_number" label="OEM number" col="col-6" id="qp-oem" />
                <x-form.input name="sku" label="SKU" col="col-6" id="qp-sku" />
                <x-form.select name="category_id" label="Category" :options="\App\Models\PartCategory::orderBy('name')->pluck('name', 'id')" placeholder="—" col="col-12" id="qp-category" />
                <x-form.input name="purchase_cost" type="number" step="0.01" min="0" label="Purchase cost" value="0" required col="col-6" id="qp-cost" />
                <x-form.input name="selling_price" type="number" step="0.01" min="0" label="Selling price" value="0" required col="col-6" id="qp-price" />
            </div>
            <div class="form-text">Stock starts at 0 — it is added when you receive the purchase order.</div>
            <div class="text-end mt-3"><button class="btn btn-primary">Save part</button></div>
        </form>
    </x-modal>
@endcan
@stack('modals')
@stack('scripts')
</body>
</html>
