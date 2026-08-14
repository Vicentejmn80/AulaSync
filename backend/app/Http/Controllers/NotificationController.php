<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->where('colegio_id', $request->user()->colegio_id)
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'message', 'link', 'read_at', 'created_at']);

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('colegio_id', $request->user()->colegio_id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('colegio_id', $request->user()->colegio_id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('colegio_id', $request->user()->colegio_id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('colegio_id', $request->user()->colegio_id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas.',
            'unread_count' => 0,
        ]);
    }
}
