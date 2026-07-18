<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Exceptions\BusinessException;
use App\Jobs\SendAppNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class NotificationService
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function createForUser(
        User $user,
        string $title,
        string $message,
        NotificationType $type,
        ?array $data = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        $notification = $this->storeNotification(
            userId: $user->id,
            adminId: null,
            title: $title,
            message: $message,
            type: $type,
            data: $data,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );

        $this->dispatchPushForNotification($notification);

        return $notification;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function createForAdmin(
        Admin $admin,
        string $title,
        string $message,
        NotificationType $type,
        ?array $data = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        return $this->storeNotification(
            userId: null,
            adminId: $admin->id,
            title: $title,
            message: $message,
            type: $type,
            data: $data,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function queueForUser(
        User $user,
        string $title,
        string $message,
        NotificationType $type,
        ?array $data = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        $notification = $this->storeNotification(
            userId: $user->id,
            adminId: null,
            title: $title,
            message: $message,
            type: $type,
            data: $data,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );

        SendAppNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function queueForAdmin(
        Admin $admin,
        string $title,
        string $message,
        NotificationType $type,
        ?array $data = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        $notification = $this->storeNotification(
            userId: null,
            adminId: $admin->id,
            title: $title,
            message: $message,
            type: $type,
            data: $data,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );

        SendAppNotificationJob::dispatch($notification->id);

        return $notification;
    }

    public function deliverQueuedNotification(int $notificationId): void
    {
        $notification = AppNotification::query()->find($notificationId);

        if ($notification === null || $notification->user_id === null) {
            return;
        }

        $this->dispatchPushForNotification($notification);
    }

    public function paginateForUser(
        User $user,
        int $page,
        int $perPage,
        ?bool $isRead = null,
    ): LengthAwarePaginator {
        $query = AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($isRead !== null) {
            $query->where('is_read', $isRead);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function unreadCountForUser(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function findForUser(User $user, int $notificationId): AppNotification
    {
        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->first();

        if ($notification === null) {
            throw new BusinessException(
                'Notification not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $notification;
    }

    public function markAsRead(User $user, AppNotification $notification): AppNotification
    {
        if ($notification->user_id !== $user->id) {
            throw new BusinessException(
                'Notification not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($notification->is_read) {
            return $notification;
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $notification->fresh();
    }

    public function markAsReadForAdmin(AppNotification $notification): AppNotification
    {
        if ($notification->admin_id === null) {
            throw new BusinessException(
                'Only admin notifications can be marked as read here.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($notification->is_read) {
            return $notification;
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $notification->fresh();
    }

    public function markAllAsReadForUser(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    private function dispatchPushForNotification(AppNotification $notification): void
    {
        if ($notification->user_id === null) {
            return;
        }

        $claimed = false;

        DB::transaction(function () use ($notification, &$claimed): void {
            /** @var AppNotification|null $locked */
            $locked = AppNotification::query()
                ->lockForUpdate()
                ->whereKey($notification->id)
                ->first();

            if ($locked === null) {
                return;
            }

            $data = $locked->data ?? [];

            if (($data['push_dispatched'] ?? false) === true) {
                return;
            }

            $data['push_dispatched'] = true;
            $locked->update(['data' => $data]);
            $claimed = true;
        });

        if (! $claimed) {
            return;
        }

        $notification->refresh();

        SendPushNotificationJob::dispatch(
            userId: (int) $notification->user_id,
            title: $notification->title,
            body: $notification->message,
            data: $this->buildPushData(
                $notification->type,
                $notification->data,
                $notification->reference_type,
                $notification->reference_id,
            ),
            notificationId: $notification->id,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function storeNotification(
        ?int $userId,
        ?int $adminId,
        string $title,
        string $message,
        NotificationType $type,
        ?array $data,
        ?string $referenceType,
        ?int $referenceId,
    ): AppNotification {
        return AppNotification::query()->create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function buildPushData(
        NotificationType $type,
        ?array $data,
        ?string $referenceType,
        ?int $referenceId,
    ): array {
        $payload = array_merge(
            [
                'type' => $type->value,
                'reference_type' => $referenceType ?? '',
                'reference_id' => $referenceId,
            ],
            $data ?? [],
        );

        unset($payload['push_dispatched'], $payload['push_sent']);

        return $payload;
    }
}
