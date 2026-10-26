@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($breadcrumbs)
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                @foreach($breadcrumbs as $label => $url)
                    @if($url)<li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @else<li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>@endif
                @endforeach
            </ol></nav>
        @elseif($subtitle)
            <div class="text-secondary small">{{ $subtitle }}</div>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
</div>
