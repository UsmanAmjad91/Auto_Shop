@foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'error', 'info' => 'info'] as $key => $type)
    @if (session($key))<span class="d-none" data-flash="{{ session($key) }}" data-type="{{ $type }}"></span>@endif
@endforeach
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        @if($errors->count() === 1) {{ $errors->first() }}
        @else <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-1 small">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('import_errors'))
    <div class="alert alert-warning"><strong>Rows not imported:</strong>
        <ul class="small mb-0" style="max-height:200px;overflow:auto">@foreach (session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
