<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notifikasi in-app (database channel) milik user yang login.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(
            min((int) $request->query('per_page', 15), 100),
        );

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAsRead(Request $request, string $id): Response
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}
