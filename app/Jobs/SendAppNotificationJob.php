<?php

namespace App\Jobs;

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

    public function __construct(
        public int $notificationId,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->deliverQueuedNotification($this->notificationId);
    }
}
