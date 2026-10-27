@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<x-page-header title="Reports & Analytics" :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]" />
<div class="alert alert-light border small"><strong>Revenue</strong> = invoiced amounts (accrual) · <strong>Cash collected</strong> = actual receipts net of refunds · <strong>Receivables</strong> = unpaid invoice balances (lender amounts shown separately) ·
    <strong>Expenses</strong> = parts purchases + vehicle expenses + payroll · <strong>Estimated profit</strong> = revenue − captured costs (excludes overheads).</div>
<div class="row g-3">
@forelse($groups as $group => $reports)
    <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header">{{ $group }}</div><div class="list-group list-group-flush">
        @foreach($reports as $key => $r)<a href="{{ route('reports.show', $key) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">{{ $r['title'] }} <i class="bi bi-chevron-right small"></i></a>@endforeach
    </div></div></div>
@empty <div class="col"><div class="alert alert-info">No reports are available for your role.</div></div> @endforelse
</div>
@endsection
