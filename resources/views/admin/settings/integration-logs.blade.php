@extends('admin.settings._layout')
@section('settings')
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0 small align-middle">
    <thead><tr><th>When</th><th>Integration</th><th>Action</th><th>Result</th><th>HTTP</th><th>ms</th><th>Message</th></tr></thead>
    <tbody>@forelse($logs as $l)
        <tr><td class="text-nowrap">{{ $l->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $l->integration }}</td><td>{{ $l->action }}</td>
            <td>{!! $l->successful ? '<span class="text-success">OK</span>' : '<span class="text-danger">Failed</span>' !!}</td><td>{{ $l->status_code }}</td><td>{{ $l->duration_ms }}</td>
            <td>{{ \Illuminate\Support\Str::limit($l->message, 120) }}</td></tr>
    @empty<tr><td colspan="7" class="text-secondary">No integration calls logged yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
