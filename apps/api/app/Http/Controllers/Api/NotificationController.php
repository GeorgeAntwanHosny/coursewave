<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Both relations are scoped to the owner and ordered newest first.
        $query = $request->boolean('unread') ? $user->unreadNotifications() : $user->notifications();
        $page = $query->paginate(15);

        return ApiResponse::success([
            'items' => NotificationResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'Notifications retrieved.');
    }

    public function unreadCount(Request $request)
    {
        return ApiResponse::success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Unread count retrieved.');
    }

    /** Idempotent: marking an already-read notification keeps its original read_at. */
    public function markRead(Request $request, string $id)
    {
        // Query-scope layer: another user's notification id looks exactly like a missing one.
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if (! $notification) {
            return ApiResponse::error('Notification not found.', null, 404);
        }

        $notification->markAsRead();

        return ApiResponse::success(new NotificationResource($notification->fresh()), 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['marked' => $updated], 'All notifications marked as read.');
    }
}
