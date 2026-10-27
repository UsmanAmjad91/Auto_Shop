@extends('layouts.app')
@section('title', 'My Payslips')
@section('content')
<x-page-header title="My payslips" />
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Net</th><th class="text-end">Paid</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($entries as $e)<tr><td>{{ $e->period->name }}</td><td class="text-end money">{{ money($e->gross_earnings) }}</td><td class="text-end money">{{ money($e->net_pay) }}</td>
        <td class="text-end money">{{ money($e->amount_paid) }}</td><td><x-status :status="$e->payment_status" /></td>
        <td class="text-end"><a href="{{ route('payroll.entries.payslip', $e) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Payslip</a></td></tr>
    @empty<tr><td colspan="6" class="text-secondary">No approved payslips yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
