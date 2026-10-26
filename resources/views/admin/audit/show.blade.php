@extends('layouts.app')
@section('title', 'Audit entry')
@section('content')
<x-page-header title="Audit entry #{{ $log->id }}" :breadcrumbs="['Audit logs' => route('audit-logs.index'), '#'.$log->id => null]" />
<div class="card mb-3"><dl class="dl-grid card-body">
    <dt>When</dt><dd>{{ $log->created_at }}</dd><dt>User</dt><dd>{{ $log->user?->name ?? 'System' }}</dd>
    <dt>Event</dt><dd>{{ $log->event }}</dd><dt>Record</dt><dd>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</dd>
    <dt>Description</dt><dd>{{ $log->description }}</dd><dt>IP / agent</dt><dd>{{ $log->ip_address }} · {{ $log->user_agent }}</dd>
</dl></div>
<div class="card"><div class="card-header">Changes</div><div class="table-responsive"><table class="table table-sm mb-0 small">
    <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
    <tbody>
    @foreach(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
        <tr><td class="fw-semibold">{{ $field }}</td>
            <td class="text-danger">{{ is_scalar($log->old_values[$field] ?? null) ? $log->old_values[$field] : json_encode($log->old_values[$field] ?? null) }}</td>
            <td class="text-success">{{ is_scalar($log->new_values[$field] ?? null) ? $log->new_values[$field] : json_encode($log->new_values[$field] ?? null) }}</td></tr>
    @endforeach
    </tbody></table></div></div>
@endsection
