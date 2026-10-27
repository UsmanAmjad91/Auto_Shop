@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<x-page-header title="Notifications">
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
</x-page-header>
<div class="card"><div class="list-group list-group-flush">
    @forelse($notifications as $n)
        <form method="POST" action="{{ route('notifications.read', $n->id) }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'bg-body-tertiary' }}">@csrf
            <button class="btn btn-link text-start text-decoration-none text-body p-0 w-100 d-flex gap-3">
                <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} fs-4 text-{{ $n->data['level'] ?? 'secondary' }}"></i>
                <span class="flex-fill"><span class="fw-semibold d-block">{{ $n->data['title'] ?? 'Notification' }}</span><span class="small">{{ $n->data['message'] ?? '' }}</span></span>
                <span class="small text-secondary text-nowrap">{{ $n->created_at->diffForHumans() }}</span>
            </button>
        </form>
    @empty <div class="empty-state"><i class="bi bi-bell-slash"></i>No notifications yet.</div> @endforelse
</div></div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
