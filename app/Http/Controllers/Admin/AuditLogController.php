<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\DataTable;
use App\Support\Exporter;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('admin.audit.index', ['events' => AuditLog::distinct()->orderBy('event')->pluck('event'),
                'types' => AuditLog::whereNotNull('auditable_type')->distinct()->pluck('auditable_type')->map(fn ($t) => class_basename($t))->unique()->values()]);
        }

        return DataTable::make(AuditLog::with('user:id,name'), $request)
            ->searchable(['description', 'event', 'ip_address', 'user.name'])
            ->sortable(['created_at' => 'created_at', 'event' => 'event'], 'created_at')
            ->filters(function ($q, $r) {
                $q->when($r->event, fn ($x) => $x->where('event', $r->event))
                    ->when($r->type, fn ($x) => $x->where('auditable_type', 'like', '%\\'.$r->type))
                    ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                    ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to));
            })
            ->transform(fn (AuditLog $l) => [
                'id' => $l->id, 'created_at' => $l->created_at->format('Y-m-d H:i:s'), 'user' => $l->user?->name ?? 'System',
                'event' => $l->event, 'record' => $l->auditable_type ? class_basename($l->auditable_type).' #'.$l->auditable_id : '',
                'description' => $l->description, 'ip' => $l->ip_address, 'url' => route('audit-logs.show', $l),
            ])->response();
    }

    public function show(AuditLog $auditLog)
    {
        return view('admin.audit.show', ['log' => $auditLog->load('user')]);
    }

    public function export(Request $request)
    {
        $rows = AuditLog::with('user:id,name')
            ->when($request->event, fn ($q) => $q->where('event', $request->event))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest('id')->limit(20000)->get()
            ->map(fn ($l) => ['when' => $l->created_at->toDateTimeString(), 'user' => $l->user?->name ?? 'System', 'event' => $l->event,
                'record' => $l->auditable_type ? class_basename($l->auditable_type).' #'.$l->auditable_id : '', 'description' => $l->description, 'ip' => $l->ip_address]);

        return Exporter::download($request->input('format', 'csv'), 'Audit Log', ['when' => 'When', 'user' => 'User', 'event' => 'Event',
            'record' => 'Record', 'description' => 'Description', 'ip' => 'IP'], $rows);
    }
}
