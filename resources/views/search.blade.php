@extends('layouts.app')
@section('title', 'Search')
@section('content')
<x-page-header title="Search" />
<form class="mb-3" method="GET"><div class="input-group"><input name="q" value="{{ $term }}" class="form-control" placeholder="Search everything…" autofocus><button class="btn btn-primary">Search</button></div></form>
@if(mb_strlen($term) < 2)
    <div class="text-secondary small">Type at least 2 characters.</div>
@elseif(! $results)
    <div class="empty-state card"><i class="bi bi-search"></i>No results for “{{ $term }}”.</div>
@else
    @foreach($results as $group => $items)
        <div class="card mb-3"><div class="card-header">{{ $group }} <span class="badge text-bg-secondary">{{ count($items) }}</span></div>
            <div class="list-group list-group-flush">@foreach($items as $i)
                <a href="{{ $i['url'] }}" class="list-group-item list-group-item-action"><i class="bi bi-{{ $i['icon'] }} me-2 text-secondary"></i><strong>{{ $i['title'] }}</strong><div class="small text-secondary ms-4">{{ $i['subtitle'] }}</div></a>
            @endforeach</div></div>
    @endforeach
@endif
@endsection
