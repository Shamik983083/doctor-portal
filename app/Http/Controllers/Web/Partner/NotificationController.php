<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($request->ajax() || $request->wantsJson()) {
            $notifications = $user->notifications()
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($n) => [
                    'id'      => $n->id,
                    'type'    => $n->data['type'] ?? 'info',
                    'title'   => $n->data['title'] ?? '',
                    'body'    => $n->data['body'] ?? '',
                    'url'     => $n->data['url'] ?? '#',
                    'is_read' => !is_null($n->read_at),
                    'time'    => $n->created_at->diffForHumans(),
                ]);

            return response()->json([
                'notifications' => $notifications,
                'unread'        => $user->unreadNotifications()->count(),
            ]);
        }

        $notifications = $user->notifications()->latest()->paginate(25);
        $unread        = $user->unreadNotifications()->count();

        return view('partner.notifications.index', compact('notifications', 'unread'));
    }

    public function markRead(string $id)
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['ok' => true]);
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return redirect()->route('partner.notifications.index')
            ->with('success', 'All notifications marked as read.');
    }
}
