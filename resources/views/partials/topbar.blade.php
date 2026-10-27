@php $u = auth()->user(); @endphp
<header class="app-topbar">
    <button class="btn btn-sm btn-outline-secondary border-0" data-sidebar-toggle aria-label="Toggle navigation"><i class="bi bi-list fs-5"></i></button>

    <div class="global-search d-none d-sm-block">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" placeholder="Search vehicles, VINs, customers, parts, invoices… (Ctrl+K)" aria-label="Global search" autocomplete="off">
        </div>
        <div class="results dropdown-menu show d-none shadow"></div>
    </div>

    <div class="ms-auto d-flex align-items-center gap-1">
        <a href="{{ route('search') }}" class="btn btn-sm btn-outline-secondary border-0 d-sm-none" aria-label="Search"><i class="bi bi-search"></i></a>

        @canany(['vehicles.create', 'parts.create', 'customers.create', 'service_jobs.create', 'invoices.create', 'payments.create'])
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i><span class="d-none d-md-inline"> Quick Add</span></button>
            <ul class="dropdown-menu dropdown-menu-end">
                @can('vehicles.create')<li><a class="dropdown-item" href="{{ route('vehicles.create') }}"><i class="bi bi-car-front me-2"></i>Vehicle</a></li>@endcan
                @can('parts.create')<li><a class="dropdown-item" href="{{ route('parts.create') }}"><i class="bi bi-box-seam me-2"></i>Part</a></li>@endcan
                @can('customers.create')<li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#quickCustomerModal"><i class="bi bi-person-plus me-2"></i>Customer</a></li>@endcan
                @can('service_jobs.create')
                    <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['category' => 'repair']) }}"><i class="bi bi-tools me-2"></i>Repair job</a></li>
                    <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['category' => 'interior']) }}"><i class="bi bi-lamp me-2"></i>Interior job</a></li>
                    <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['category' => 'cleaning']) }}"><i class="bi bi-droplet-half me-2"></i>Wash / detailing job</a></li>
                @endcan
                @can('invoices.create')<li><a class="dropdown-item" href="{{ route('invoices.create') }}"><i class="bi bi-receipt me-2"></i>Invoice</a></li>@endcan
                @can('payments.create')<li><a class="dropdown-item" href="{{ route('payments.create') }}"><i class="bi bi-cash-coin me-2"></i>Payment</a></li>@endcan
            </ul>
        </div>
        @endcanany

        <div class="dropdown" id="notif-dropdown">
            <button class="btn btn-sm btn-outline-secondary border-0 position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Notifications">
                <i class="bi bi-bell fs-5"></i>
                <span class="notif-count position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger {{ $unreadNotifications ? '' : 'd-none' }}">{{ $unreadNotifications }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width:340px">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong class="small">Notifications</strong><a href="#" class="small notif-read-all">Mark all read</a>
                </div>
                <div class="notif-list" style="max-height:60vh;overflow:auto"><div class="p-3 small text-secondary">Loading…</div></div>
                <a href="{{ route('notifications.index') }}" class="d-block text-center small py-2 border-top">View all</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="dropdown" aria-label="Theme"><i class="theme-icon bi bi-circle-half fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" data-theme-set="light"><i class="bi bi-sun me-2"></i>Light</a></li>
                <li><a class="dropdown-item" href="#" data-theme-set="dark"><i class="bi bi-moon-stars me-2"></i>Dark</a></li>
                <li><a class="dropdown-item" href="#" data-theme-set="auto"><i class="bi bi-circle-half me-2"></i>Automatic</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2 border-0" data-bs-toggle="dropdown" aria-label="Account">
                <img src="{{ $u->avatarUrl() }}" alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                <span class="d-none d-lg-inline text-start lh-sm"><span class="d-block small fw-semibold">{{ $u->name }}</span><span class="d-block text-secondary" style="font-size:.7rem">{{ $u->getRoleNames()->first() }}</span></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.security') }}"><i class="bi bi-shield-lock me-2"></i>Security & sessions</a></li>
                @if($u->employee)<li><a class="dropdown-item" href="{{ route('payroll.mine') }}"><i class="bi bi-wallet2 me-2"></i>My payslips</a></li>@endif
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form></li>
            </ul>
        </div>
    </div>
</header>
