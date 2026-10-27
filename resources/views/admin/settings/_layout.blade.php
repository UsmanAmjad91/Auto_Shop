@extends('layouts.app')
@section('title', $sections[$section]['title'].' Settings')
@section('content')
<x-page-header :title="$sections[$section]['title']" :breadcrumbs="['Settings' => route('settings.index'), $sections[$section]['title'] => null]" />
<div class="row g-3">
    <div class="col-lg-3 d-none d-lg-block">@include('admin.settings._nav')</div>
    <div class="col-lg-9">@yield('settings')</div>
</div>
@endsection
