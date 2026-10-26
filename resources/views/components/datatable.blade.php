@props(['id', 'url', 'columns', 'module' => null, 'sort' => null, 'dir' => 'desc', 'bulk' => [], 'bulkUrl' => null, 'exportUrl' => null, 'cards' => false, 'sync' => true])
<div id="{{ $id }}" data-datatable data-url="{{ $url }}" data-module="{{ $module ?? $id }}" data-columns='@json(array_values($columns))'
     data-sort="{{ $sort }}" data-dir="{{ $dir }}" data-bulk='@json($bulk)' @if($bulkUrl) data-bulk-url="{{ $bulkUrl }}" @endif
     @if($exportUrl) data-export-url="{{ $exportUrl }}" @endif @if($cards) data-cards @endif data-sync-url="{{ $sync ? 'true' : 'false' }}" {{ $attributes }}>
    <div class="p-5 text-center text-secondary"><span class="spinner-border spinner-border-sm"></span> Loading…</div>
</div>
