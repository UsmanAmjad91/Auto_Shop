@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => null, 'prefix' => null, 'suffix' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $val = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : ($type === 'time' ? 'H:i' : 'Y-m-d')) : $value);
@endphp
@if($col)<div class="{{ $col }}">@endif
    @if($label)<label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>@endif
    @if($prefix || $suffix)<div class="input-group">@endif
        @if($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $val }}" @if($required) required @endif
            {{ $attributes->except('id')->merge(['class' => ($type === 'color' ? 'form-control form-control-color' : 'form-control').($errors->has($key) ? ' is-invalid' : '')]) }}>
        @if($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
    @if($prefix || $suffix)</div>@endif
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
@if($col)</div>@endif
