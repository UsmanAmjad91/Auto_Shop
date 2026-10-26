{{-- Private attachments for customers / suppliers / employees / parts --}}
<div class="card"><div class="card-header">Attachments</div>
    <ul class="list-group list-group-flush small">
        @forelse($model->attachments as $a)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip"></i> <a href="{{ route('files.attachment', $a) }}">{{ $a->original_name }}</a>
                    <span class="text-secondary">· {{ number_format($a->size / 1024) }} KB · {{ fmt_date($a->created_at) }}</span></span>
                @can($module.'.edit')<a href="{{ route('files.attachment.destroy', $a) }}" data-method="DELETE" data-confirm="Delete attachment?" class="text-danger"><i class="bi bi-trash"></i></a>@endcan
            </li>
        @empty <li class="list-group-item text-secondary">No attachments.</li> @endforelse
    </ul>
    @can($module.'.edit')
        <form method="POST" action="{{ route('files.attachment.store') }}" enctype="multipart/form-data" class="card-footer input-group input-group-sm">@csrf
            <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $model->id }}">
            <input type="file" name="files[]" multiple class="form-control" required><button class="btn btn-outline-primary">Upload</button>
        </form>
    @endcan
</div>
