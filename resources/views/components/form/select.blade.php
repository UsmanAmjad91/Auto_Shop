@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'col' => null, 'searchable' => false])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $current = old($key, $value);
@endphp
@if($col)<div class="{{ $col }}">@endif
    @if($label)<label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" @if($required) required @endif @if($searchable) data-searchable @endif
        {{ $attributes->except('id')->merge(['class' => 'form-select'.($errors->has($key) ? ' is-invalid' : '')]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
@if($col)</div>@endif
