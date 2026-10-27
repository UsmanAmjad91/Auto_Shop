@props(['status' => null, 'label' => null, 'color' => null])
<span {{ $attributes->merge(['class' => 'badge text-bg-'.($color ?? status_color($status))]) }}>{{ $label ?? status_label($status) }}</span>
