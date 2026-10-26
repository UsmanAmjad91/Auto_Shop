@props(['name', 'label' => null, 'value' => null, 'required' => false, 'rows' => 3, 'help' => null, 'col' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name)); @endphp
@if($col)<div class="{{ $col }}">@endif
    @if($label)<label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @if($required) required @endif
        {{ $attributes->except('id')->merge(['class' => 'form-control'.($errors->has($key) ? ' is-invalid' : '')]) }}>{{ old($key, $value) }}</textarea>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
@if($col)</div>@endif
