@extends('admin.settings._layout')
@section('settings')
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <div class="me-auto"><div class="fw-semibold">Database backups</div>
        <div class="small text-secondary">Scheduled backups: <strong>{{ settings()->bool('backup.enabled') ? ucfirst(settings('backup.frequency')).' at 02:00' : 'disabled' }}</strong>,
            kept {{ settings('backup.keep_days') }} days (change in <a href="{{ route('settings.section', 'operations') }}">Operations</a>). Requires the Laravel scheduler cron and <code>mysqldump</code>.</div></div>
    @can('backups.manage')<form method="POST" action="{{ route('settings.backups.run') }}">@csrf<button class="btn btn-primary"><i class="bi bi-database-add"></i> Back up now</button></form>@endcan
</div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
    <tbody>@forelse($backups as $b)
        <tr><td class="font-monospace small">{{ $b['file'] }}</td><td>{{ number_format($b['size'] / 1024, 1) }} KB</td><td>{{ date('Y-m-d H:i', $b['date']) }}</td>
            <td class="text-end">@can('backups.manage')<a href="{{ route('settings.backups.download', $b['file']) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>@endcan</td></tr>
    @empty<tr><td colspan="4" class="text-secondary small">No backups yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-header">Restore procedure</div><div class="card-body small">
    <ol class="mb-0">
        <li>Enable maintenance mode in <a href="{{ route('settings.section', 'security') }}">Security & Maintenance</a> (or run <code>php artisan down</code>).</li>
        <li>Download the backup file and copy it to the database server.</li>
        <li>Restore: <code>gunzip &lt; backup-YYYY-MM-DD_HHMMSS.sql.gz | mysql -u USER -p DATABASE</code></li>
        <li>Run <code>php artisan migrate --force</code> (if restoring into a newer app version) and <code>php artisan optimize:clear</code>.</li>
        <li>Disable maintenance mode. Uploaded files live in <code>storage/app</code> — back that folder up separately.</li>
    </ol></div></div>
@endsection
