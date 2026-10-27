@props(['name', 'label', 'checked' => false, 'help' => null, 'col' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name)); @endphp
@if($col)<div class="{{ $col }}">@endif
    <input type="hidden" name="{{ $name }}" value="0">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" id="{{ $id }}" value="1" @checked(old($key, $checked)) {{ $attributes->except('id') }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    </div>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
@if($col)</div>@endif
