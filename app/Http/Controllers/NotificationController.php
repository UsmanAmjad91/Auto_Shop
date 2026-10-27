<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['notifications' => $request->user()->notifications()->paginate(25)]);
    }

    public function feed(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(10)->get()->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->data['title'] ?? 'Notification', 'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null, 'icon' => $n->data['icon'] ?? 'bell', 'level' => $n->data['level'] ?? 'secondary',
                'read' => (bool) $n->read_at, 'time' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect($n->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->done($request, 'All notifications marked as read.');
    }
}
