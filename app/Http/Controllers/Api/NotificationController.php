<?php

namespace App\Http\Controllers\Api;

use App\Models\Player;
use App\Models\PlayerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    /**
     * Get player's notifications
     */
    public function index(string $playerUuid, Request $request): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $query = PlayerNotification::where('player_id', $player->id);

        // Filter by read status if specified
        if ($request->has('read')) {
            $query->where('is_read', filter_var($request->get('read'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by type if specified
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit($request->get('limit', 50))
            ->get();

        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data ?? [],
                'is_read' => $notification->is_read,
                'priority' => $notification->priority ?? 'normal',
                'created_at' => $notification->created_at?->toIso8601String(),
                'read_at' => $notification->read_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'total_notifications' => $notifications->count(),
            'unread_count' => PlayerNotification::where('player_id', $player->id)
                ->where('is_read', false)
                ->count(),
            'notifications' => $formattedNotifications,
        ], 'Notifications retrieved successfully');
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $unreadCount = PlayerNotification::where('player_id', $player->id)
            ->where('is_read', false)
            ->count();

        $unreadByType = PlayerNotification::where('player_id', $player->id)
            ->where('is_read', false)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        return $this->success([
            'player' => [
                'uuid' => $player->uuid,
                'call_sign' => $player->call_sign,
            ],
            'total_unread' => $unreadCount,
            'unread_by_type' => $unreadByType,
        ], 'Unread count retrieved successfully');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $playerUuid, int $notificationId): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $notification = PlayerNotification::where('id', $notificationId)
            ->where('player_id', $player->id)
            ->firstOrFail();

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $this->success([
            'notification_id' => $notification->id,
            'is_read' => true,
            'read_at' => $notification->read_at?->toIso8601String(),
        ], 'Notification marked as read');
    }

    /**
     * Delete notification
     */
    public function destroy(string $playerUuid, int $notificationId): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $notification = PlayerNotification::where('id', $notificationId)
            ->where('player_id', $player->id)
            ->firstOrFail();

        $notification->delete();

        return $this->success(null, 'Notification deleted successfully');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $updated = PlayerNotification::where('player_id', $player->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->success([
            'notifications_marked' => $updated,
        ], 'All notifications marked as read');
    }

    /**
     * Clear all read notifications
     */
    public function clearRead(string $playerUuid): JsonResponse
    {
        $player = Player::where('uuid', $playerUuid)->firstOrFail();

        $deleted = PlayerNotification::where('player_id', $player->id)
            ->where('is_read', true)
            ->delete();

        return $this->success([
            'notifications_cleared' => $deleted,
        ], 'Read notifications cleared successfully');
    }
}
