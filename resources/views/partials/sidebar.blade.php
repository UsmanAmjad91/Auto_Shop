@php
    $u = auth()->user();
    $link = function (string $route, string $icon, string $label, $active = null, array $params = [], ?string $badge = null) {
        $isActive = $active ? request()->routeIs($active) : request()->routeIs($route);
        if ($params && $isActive) {
            foreach ($params as $k => $v) { if (request($k) != $v) { $isActive = false; } }
        }

        return '<a href="'.e(route($route, $params)).'" class="nav-link '.($isActive ? 'active' : '').'" '.($isActive ? 'aria-current="page"' : '').' title="'.e($label).'">'
            .'<i class="bi bi-'.e($icon).'"></i><span class="nav-text">'.e($label).'</span>'.($badge ? '<span class="badge rounded-pill text-bg-danger">'.e($badge).'</span>' : '').'</a>';
    };
    $pending = $u->can('users.approve') ? \App\Models\User::where('status', 'pending')->count() : 0;
    $lowStock = $u->can('parts.view') ? \App\Models\Part::whereIn('stock_status', ['low_stock', 'out_of_stock'])->count() : 0;
    $jobCat = request()->routeIs('service-jobs.*') ? request('category') : null;
@endphp
<aside class="app-sidebar" aria-label="Main navigation">
    <a href="{{ route('dashboard') }}" class="brand">
        @if(settings('general.logo'))
            <img src="{{ asset('storage/'.settings('general.logo')) }}" alt="">
        @else
            <span class="brand-icon"><i class="bi bi-car-front-fill"></i></span>
        @endif
        <span class="brand-text text-truncate">{{ settings('general.company_name') }}</span>
    </a>
    <nav class="nav flex-column">
        @can('dashboard.view'){!! $link('dashboard', 'speedometer2', 'Dashboard') !!}@endcan

        @canany(['vehicles.view', 'parts.view', 'purchase_orders.view'])<div class="nav-section">Inventory</div>@endcanany
        @can('vehicles.view'){!! $link('vehicles.index', 'car-front', 'Vehicles', 'vehicles.*') !!}@endcan
        @can('parts.view'){!! $link('parts.index', 'box-seam', 'Parts Inventory', 'parts.*', [], $lowStock ?: null) !!}@endcan
        @can('purchase_orders.view'){!! $link('purchase-orders.index', 'cart3', 'Purchase Orders', 'purchase-orders.*') !!}@endcan

        @can('service_jobs.view')
            <div class="nav-section">Service</div>
            <a href="{{ route('service-jobs.index', ['category' => 'repair']) }}" class="nav-link {{ $jobCat === 'repair' ? 'active' : '' }}" title="Repair Jobs"><i class="bi bi-tools"></i><span class="nav-text">Repair Jobs</span></a>
            <a href="{{ route('service-jobs.index', ['category' => 'interior']) }}" class="nav-link {{ $jobCat === 'interior' ? 'active' : '' }}" title="Interior Services"><i class="bi bi-lamp"></i><span class="nav-text">Interior Services</span></a>
            <a href="{{ route('service-jobs.index', ['category' => 'cleaning']) }}" class="nav-link {{ $jobCat === 'cleaning' ? 'active' : '' }}" title="Washing & Detailing"><i class="bi bi-droplet-half"></i><span class="nav-text">Washing &amp; Detailing</span></a>
            <a href="{{ route('service-jobs.index') }}" class="nav-link {{ request()->routeIs('service-jobs.*') && ! $jobCat ? 'active' : '' }}" title="All Jobs"><i class="bi bi-kanban"></i><span class="nav-text">All Jobs</span></a>
        @endcan
        @can('tasks.view'){!! $link('tasks.index', 'list-check', 'Tasks', 'tasks.*') !!}@endcan

        @canany(['employees.view', 'attendance.view', 'payroll.view'])<div class="nav-section">People</div>@endcanany
        @can('employees.view'){!! $link('employees.index', 'person-badge', 'Employees', 'employees.*') !!}@endcan
        @can('attendance.view'){!! $link('attendance.index', 'clock-history', 'Attendance', 'attendance.*') !!}@endcan
        @can('payroll.view'){!! $link('payroll.index', 'wallet2', 'Payroll', ['payroll.index', 'payroll.show', 'payroll.payments']) !!}@endcan
        @if($u->employee && ! $u->can('payroll.view')){!! $link('payroll.mine', 'wallet2', 'My Payslips') !!}@endif

        @canany(['customers.view', 'suppliers.view'])<div class="nav-section">Contacts</div>@endcanany
        @can('customers.view'){!! $link('customers.index', 'people', 'Customers', 'customers.*') !!}@endcan
        @can('suppliers.view'){!! $link('suppliers.index', 'truck', 'Suppliers', 'suppliers.*') !!}@endcan

        @canany(['invoices.view', 'payments.view', 'reports.view'])<div class="nav-section">Finance</div>@endcanany
        @can('invoices.view'){!! $link('invoices.index', 'receipt', 'Sales & Invoices', 'invoices.*') !!}@endcan
        @can('payments.view'){!! $link('payments.index', 'cash-coin', 'Payments', 'payments.*') !!}@endcan
        @can('reports.view'){!! $link('reports.index', 'bar-chart-line', 'Reports', 'reports.*') !!}@endcan

        @canany(['users.view', 'roles.view', 'settings.manage', 'audit_logs.view'])<div class="nav-section">Administration</div>@endcanany
        @can('users.view'){!! $link('users.index', 'person-gear', 'Users', 'users.*', [], $pending ?: null) !!}@endcan
        @can('roles.view'){!! $link('roles.index', 'shield-lock', 'Roles & Permissions', 'roles.*') !!}@endcan
        @can('audit_logs.view'){!! $link('audit-logs.index', 'journal-text', 'Audit Logs', 'audit-logs.*') !!}@endcan
        @can('settings.manage'){!! $link('settings.index', 'gear', 'System Settings', 'settings.*') !!}@endcan
    </nav>
</aside>
