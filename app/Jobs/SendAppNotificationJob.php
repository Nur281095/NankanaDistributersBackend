<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Admin;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public ?int $userId,
        public ?int $adminId,
        public string $title,
        public string $message,
        public NotificationType $type,
        public ?array $data = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if ($this->userId !== null) {
            $user = User::query()->find($this->userId);

            if ($user === null) {
                return;
            }

            $notificationService->createForUser(
                user: $user,
                title: $this->title,
                message: $this->message,
                type: $this->type,
                data: $this->data,
                referenceType: $this->referenceType,
                referenceId: $this->referenceId,
            );

            return;
        }

        if ($this->adminId === null) {
            return;
        }

        $admin = Admin::query()->find($this->adminId);

        if ($admin === null) {
            return;
        }

        $notificationService->createForAdmin(
            admin: $admin,
            title: $this->title,
            message: $this->message,
            type: $this->type,
            data: $this->data,
            referenceType: $this->referenceType,
            referenceId: $this->referenceId,
        );
    }
}
