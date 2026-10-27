{{-- Searchable remote select backed by /lookup/{type}. Pass the current value and its display text. --}}
@props(['name', 'type', 'label' => null, 'value' => null, 'text' => null, 'required' => false, 'placeholder' => 'Search…', 'help' => null, 'col' => null, 'params' => [], 'create' => null, 'depends' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $current = old($key, $value);
@endphp
@if($col)<div class="{{ $col }}">@endif
    @if($label)<div class="d-flex justify-content-between align-items-baseline">
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
        @if($create)<a href="#" class="small text-decoration-none" data-quick-for="{{ $id }}"><i class="bi bi-plus-circle"></i> New</a>@endif
    </div>@endif
    <select name="{{ $name }}" id="{{ $id }}" data-remote="{{ $type }}" data-placeholder="{{ $placeholder }}" @if($required) required @endif
        @if($params) data-params='@json($params)' @endif @if($create) data-create="{{ $create }}" @endif @if($depends) data-depends="{{ $depends }}" @endif
        @if($text) data-selected-text="{{ $text }}" @endif
        {{ $attributes->except('id')->merge(['class' => 'form-select'.($errors->has($key) ? ' is-invalid' : '')]) }}>
        <option value=""></option>
        @if($current)<option value="{{ $current }}" selected>{{ $text ?? $current }}</option>@endif
    </select>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
@if($col)</div>@endif
