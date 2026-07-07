<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Notification\NotificationListRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(NotificationListRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->paginateForUser(
            user: $request->user(),
            page: $request->page(),
            perPage: $request->perPage(),
            isRead: $request->isReadFilter(),
        );

        return $this->paginated($notifications, NotificationResource::class);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'unread_count' => $this->notificationService->unreadCountForUser($request->user()),
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $record = $this->notificationService->findForUser($request->user(), $notification);

        $this->authorize('update', $record);

        $record = $this->notificationService->markAsRead($request->user(), $record);

        return $this->success(
            NotificationResource::make($record)->resolve(),
            'Notification marked as read.',
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updatedCount = $this->notificationService->markAllAsReadForUser($request->user());

        return $this->success(
            ['updated_count' => $updatedCount],
            'All notifications marked as read.',
        );
    }
}
