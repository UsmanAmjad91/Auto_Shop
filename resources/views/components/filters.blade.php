@props(['for'])
{{-- Collapsible on phones, always visible from md up. --}}
<button class="btn btn-sm btn-outline-secondary w-100 mb-2 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $for }}-filters" aria-expanded="false" aria-controls="{{ $for }}-filters">
    <i class="bi bi-funnel"></i> Filters
</button>
<form id="{{ $for }}-filters" data-dt-filters="#{{ $for }}" class="card card-body filter-panel mb-3 py-2 collapse d-md-block" autocomplete="off">
    <div class="row g-2 align-items-end">{{ $slot }}</div>
</form>
