<div class="list-group mb-3">
    @foreach($sections as $key => $s)
        <a href="{{ route('settings.section', $key) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center {{ ($section ?? '') === $key ? 'active' : '' }}">
            <i class="bi bi-{{ $s['icon'] }}"></i><span class="small">{{ $s['title'] }}</span></a>
    @endforeach
</div>
