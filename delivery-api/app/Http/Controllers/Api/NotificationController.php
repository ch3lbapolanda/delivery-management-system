<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications,
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Unread notification count retrieved successfully.',
            'data' => [
                'count' => $request->user()
                    ->unreadNotifications()
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(
        Request $request,
        string $id
    ): JsonResponse {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}

