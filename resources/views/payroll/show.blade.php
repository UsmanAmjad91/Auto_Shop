@extends('layouts.app')
@section('title', $period->name)
@section('content')
@php $u = auth()->user(); $draft = $period->status === 'draft'; @endphp
<x-page-header :title="$period->name" :breadcrumbs="['Payroll' => route('payroll.index'), $period->name => null]">
    @if($draft && $u->can('payroll.edit'))<form method="POST" action="{{ route('payroll.generate', $period) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Recalculate</button></form>@endif
    @if($draft && $u->can('payroll.approve'))<form method="POST" action="{{ route('payroll.approve', $period) }}" onsubmit="return confirm('Approve and lock this payroll?')">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> Approve</button></form>@endif
    @can('payroll.export')<a href="{{ route('payroll.export', [$period, 'format' => 'xlsx']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i></a>
        <a href="{{ route('payroll.export', [$period, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-pdf"></i></a>@endcan
    @if($draft && $u->can('payroll.delete'))<a href="{{ route('payroll.destroy', $period) }}" data-method="DELETE" data-confirm="Delete this draft payroll?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endif
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card card-body"><div class="small text-secondary">Status</div><div><x-status :status="$period->status" /></div>
        <div class="small text-secondary mt-1">{{ fmt_date($period->start_date) }} → {{ fmt_date($period->end_date) }}, pay {{ fmt_date($period->pay_date) ?: '—' }}</div>
        @if($period->approver)<div class="small text-secondary">Approved by {{ $period->approver->name }}</div>@endif</div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small text-secondary">Gross earnings</div><div class="fs-4 fw-bold">{{ money($period->total_gross) }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small text-secondary">Net pay</div><div class="fs-4 fw-bold">{{ money($period->total_net) }}</div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="small text-secondary">Paid / remaining</div><div class="fs-5 fw-bold">{{ money($period->total_paid) }} / <span class="text-warning">{{ money(round($period->total_net - $period->total_paid, 2)) }}</span></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Employee</th><th>Pay</th><th class="text-end">Hours / days</th><th class="text-end">Base</th><th class="text-end">OT pay</th><th class="text-end">Bonuses</th><th class="text-end">Gross</th>
        <th class="text-end">Deductions</th><th class="text-end">Advances</th><th class="text-end">Net</th><th class="text-end">Paid</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($period->entries as $e)
        <tr>
            <td><a href="{{ route('employees.show', $e->employee_id) }}">{{ $e->employee->full_name }}</a><div class="small text-secondary">{{ $e->employee->department?->name }}</div></td>
            <td class="small">{{ money($e->rate) }}/{{ $e->pay_type }}</td>
            <td class="text-end small">{{ $e->regular_hours }}h + {{ $e->overtime_hours }} OT<br>{{ $e->days_worked }} days</td>
            <td class="text-end money">{{ money($e->base_pay) }}</td><td class="text-end money">{{ money($e->overtime_pay) }}</td><td class="text-end money">{{ money($e->bonuses) }}</td>
            <td class="text-end money fw-semibold">{{ money($e->gross_earnings) }}</td><td class="text-end money">{{ money($e->deductions) }}</td><td class="text-end money">{{ money($e->advances) }}</td>
            <td class="text-end money fw-bold">{{ money($e->net_pay) }}</td><td class="text-end money">{{ money($e->amount_paid) }}</td><td><x-status :status="$e->payment_status" /></td>
            <td class="text-nowrap text-end">
                @if($draft && $u->can('payroll.edit'))<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#entry-{{ $e->id }}" title="Adjust"><i class="bi bi-pencil"></i></button>@endif
                @if(! $draft && $e->remaining_payable > 0 && $u->can('payroll.manage_payments'))<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#pay-{{ $e->id }}">Pay</button>@endif
                <a href="{{ route('payroll.entries.payslip', $e) }}" class="btn btn-sm btn-outline-secondary" title="Payslip PDF"><i class="bi bi-filetype-pdf"></i></a>
            </td>
        </tr>
        @push('modals')
            @if($draft && $u->can('payroll.edit'))
            <x-modal :id="'entry-'.$e->id" :title="'Adjust — '.$e->employee->full_name">
                <form method="POST" action="{{ route('payroll.entries.update', $e) }}" data-ajax data-reload>@csrf @method('PUT')
                    <div class="row g-2">
                        <x-form.input name="base_pay" type="number" step="0.01" min="0" label="Base pay" :value="$e->base_pay" col="col-6" />
                        <x-form.input name="overtime_pay" type="number" step="0.01" min="0" label="Overtime pay" :value="$e->overtime_pay" col="col-6" />
                        <x-form.input name="bonuses" type="number" step="0.01" min="0" label="Bonuses" :value="$e->bonuses" col="col-4" />
                        <x-form.input name="deductions" type="number" step="0.01" min="0" label="Deductions (tax etc.)" :value="$e->deductions" col="col-4" />
                        <x-form.input name="advances" type="number" step="0.01" min="0" label="Advances recovered" :value="$e->advances" col="col-4" />
                        <x-form.textarea name="notes" label="Notes" :value="$e->notes" rows="2" col="col-12" />
                    </div><div class="text-end mt-3"><button class="btn btn-primary">Save</button></div>
                </form>
            </x-modal>
            @endif
            @if(! $draft && $e->remaining_payable > 0 && $u->can('payroll.manage_payments'))
            <x-modal :id="'pay-'.$e->id" :title="'Pay — '.$e->employee->full_name">
                <form method="POST" action="{{ route('payroll.entries.pay', $e) }}" data-ajax data-reload>@csrf
                    <div class="row g-2">
                        <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$e->remaining_payable" label="Amount" :value="$e->remaining_payable" required col="col-6" />
                        <x-form.input name="payment_date" type="date" label="Date" :value="today()" required col="col-6" />
                        <x-form.select name="method" label="Method" :options="['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'check' => 'Check', 'other' => 'Other']" col="col-6" />
                        <x-form.input name="reference" label="Reference" col="col-6" />
                    </div><div class="text-end mt-3"><button class="btn btn-primary">Record payment</button></div>
                </form>
            </x-modal>
            @endif
        @endpush
    @empty
        <tr><td colspan="13" class="text-secondary small p-3">No entries. Employees need a pay rate effective in this period; then click Recalculate.</td></tr>
    @endforelse
    </tbody></table></div></div>
@endsection
