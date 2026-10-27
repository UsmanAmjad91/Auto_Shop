@extends('layouts.app')
@section('title', 'Audit Logs')
@section('content')
<x-page-header title="Audit Logs" :breadcrumbs="['Administration' => null, 'Audit logs' => null]" />
<x-filters for="audit-table">
    <x-form.select name="event" label="Event" :options="$events->combine($events)" placeholder="Any" col="col-6 col-md-3" />
    <x-form.select name="type" label="Record type" :options="$types->combine($types)" placeholder="Any" col="col-6 col-md-3" />
    <x-form.input name="date_from" type="date" label="From" col="col-6 col-md-2" />
    <x-form.input name="date_to" type="date" label="To" col="col-6 col-md-2" />
</x-filters>
<x-datatable id="audit-table" :url="route('audit-logs.index')" module="audit" sort="created_at" :export-url="route('audit-logs.export')" :columns="[
    ['key' => 'created_at', 'label' => 'When', 'sortable' => true],
    ['key' => 'user', 'label' => 'User'],
    ['key' => 'event', 'label' => 'Event', 'sortable' => true],
    ['key' => 'record', 'label' => 'Record'],
    ['key' => 'description', 'label' => 'Description', 'type' => 'link'],
    ['key' => 'ip', 'label' => 'IP'],
]" />
@endsection
