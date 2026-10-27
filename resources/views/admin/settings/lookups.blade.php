@extends('admin.settings._layout')
@section('settings')
<div class="row g-3">
@foreach($lookups as $type => $l)
    <div class="col-md-6"><div class="card h-100"><div class="card-header">{{ $l['title'] }}</div>
        <ul class="list-group list-group-flush small" style="max-height:320px;overflow:auto">
            @forelse($l['items'] as $item)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>@if($type === 'service_types')<span class="badge text-bg-light border">{{ $item->category }}</span> @endif{{ $item->name }}
                        @if($type === 'service_types' && ($item->default_price > 0 || $item->default_cost > 0))<span class="text-secondary">· price {{ money($item->default_price) }} / cost {{ money($item->default_cost) }}</span>@endif
                        @isset($item->employees_count)<span class="text-secondary">({{ $item->employees_count }})</span>@endisset
                        @isset($item->parts_count)<span class="text-secondary">({{ $item->parts_count }})</span>@endisset
                        @if($type === 'stock_locations' && $item->is_default)<span class="badge text-bg-primary">default</span>@endif</span>
                    <form method="POST" action="{{ route('settings.lookups.destroy', [$type, $item->id]) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>
                </li>
            @empty <li class="list-group-item text-secondary">None yet.</li> @endforelse
        </ul>
        <div class="card-footer"><form method="POST" action="{{ route('settings.lookups.store', $type) }}" class="row g-2">@csrf
            @if($type === 'service_types')
                <div class="col-4"><select name="category" class="form-select form-select-sm"><option value="repair">repair</option><option value="interior">interior</option><option value="cleaning">cleaning</option></select></div>
                <div class="col-8"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                <div class="col-5"><input name="default_price" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Default price"></div>
                <div class="col-5"><input name="default_cost" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Default cost"></div>
                <div class="col-2"><button class="btn btn-sm btn-primary w-100">Add</button></div>
            @else
                <div class="col"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                <div class="col-auto"><button class="btn btn-sm btn-primary">Add</button></div>
            @endif
        </form></div>
    </div></div>
@endforeach
</div>
@endsection
