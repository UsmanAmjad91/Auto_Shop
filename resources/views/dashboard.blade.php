@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div id="dashboard" data-layout="{{ $layout }}" class="{{ $layout === 'compact' ? 'dashboard-compact' : '' }}">
    <x-page-header title="Dashboard" :subtitle="'Welcome back, '.auth()->user()->name">
        <span class="small text-secondary align-self-center" id="dash-updated"></span>
        <button class="btn btn-sm btn-outline-secondary" id="dash-refresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </x-page-header>

    @if(auth()->user()->employee && auth()->user()->can('attendance.create'))
        <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <i class="bi bi-clock fs-4 text-primary"></i>
            <div class="me-auto">
                <div class="fw-semibold">My attendance today</div>
                <div class="small text-secondary">
                    @if($today?->clock_in) Clocked in {{ $today->clock_in->format('H:i') }}@if($today->clock_out) · out {{ $today->clock_out->format('H:i') }} · {{ $today->total_hours }} h @endif
                    @else Not clocked in yet @endif
                </div>
            </div>
            @if(! $today?->clock_in)<button class="btn btn-success btn-sm" data-punch="clock_in"><i class="bi bi-box-arrow-in-right"></i> Clock in</button>
            @elseif(! $today->clock_out)
                @if($today->break_start && ! $today->break_end)<button class="btn btn-outline-warning btn-sm" data-punch="break_end">End break</button>
                @else<button class="btn btn-outline-secondary btn-sm" data-punch="break_start">Start break</button>@endif
                <button class="btn btn-danger btn-sm" data-punch="clock_out"><i class="bi bi-box-arrow-right"></i> Clock out</button>
            @endif
        </div></div>
    @endif

    @if($layout === 'charts_first')<div class="row g-3 mb-3" id="dash-charts"></div>@endif
    <div class="row g-3 mb-3" id="dash-cards">
        @foreach($cards as $c)
            <div class="col-6 col-md-4 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex gap-3 align-items-center">
                <div class="stat-icon text-bg-{{ $c['color'] }} bg-opacity-10"><i class="bi bi-{{ $c['icon'] }}"></i></div>
                <div><div class="stat-value">{{ $c['value'] }}</div><div class="stat-label">{{ $c['label'] }}</div></div>
            </div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            @if($layout !== 'charts_first')<div class="row g-3" id="dash-charts"></div>@endif
            <div class="card mt-3"><div class="card-header">Quick actions</div><div class="card-body d-flex flex-wrap gap-2">
                @can('vehicles.create')<a href="{{ route('vehicles.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-upc-scan"></i> Add vehicle / scan VIN</a>@endcan
                @can('service_jobs.create')<a href="{{ route('service-jobs.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-tools"></i> New service job</a>@endcan
                @can('invoices.create')<a href="{{ route('invoices.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt"></i> New invoice</a>@endcan
                @can('payments.create')<a href="{{ route('payments.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-cash-coin"></i> Record payment</a>@endcan
                @can('purchase_orders.create')<a href="{{ route('purchase-orders.create', ['low_stock' => 1]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-cart-plus"></i> Reorder low stock</a>@endcan
                @can('reports.view')<a href="{{ route('reports.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart"></i> Reports</a>@endcan
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-header d-flex justify-content-between">Alerts &amp; pending approvals</div>
                <div class="list-group list-group-flush" id="dash-alerts">
                    @forelse($alerts as $a)
                        <a href="{{ $a['url'] }}" class="list-group-item list-group-item-action d-flex gap-2"><i class="bi bi-{{ $a['icon'] }} text-{{ $a['level'] }}"></i><span class="small">{{ $a['text'] }}</span></a>
                    @empty <div class="list-group-item small text-secondary">No alerts — all good.</div> @endforelse
                </div>
            </div>
            <div class="card"><div class="card-header">Recent activity</div><div class="card-body">
                <ul class="timeline" id="dash-activity">
                    @foreach($activity as $a)<li><div class="small"><strong>{{ $a['user'] }}</strong> — {{ $a['description'] ?? $a['event'] }}</div><div class="small text-secondary">{{ $a['time'] }}</div></li>@endforeach
                </ul>
            </div></div>
        </div>
    </div>
</div>
@endsection
