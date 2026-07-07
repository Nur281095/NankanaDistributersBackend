<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmPushService $fcmPushService): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $fcmPushService->sendToUser(
            user: $user,
            title: $this->title,
            body: $this->body,
            data: $this->data,
        );
    }
}
