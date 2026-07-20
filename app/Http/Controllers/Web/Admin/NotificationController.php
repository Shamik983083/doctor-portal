<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? 'info',
                'title'      => $n->data['title'] ?? '',
                'body'       => $n->data['body'] ?? '',
                'url'        => $n->data['url'] ?? '#',
                'is_read'    => !is_null($n->read_at),
                'time'       => $n->created_at->diffForHumans(),
            ]);

        $unread = Auth::user()->unreadNotifications()->count();

        return response()->json(['notifications' => $notifications, 'unread' => $unread]);
    }

    public function markRead(string $id)
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['ok' => true]);
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    }
}
