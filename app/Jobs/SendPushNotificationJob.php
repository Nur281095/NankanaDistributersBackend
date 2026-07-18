<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

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
        public ?int $notificationId = null,
    ) {}

    public function handle(FcmPushService $fcmPushService): void
    {
        if ($this->notificationId !== null && ! $this->claimPushSend()) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        try {
            $fcmPushService->sendToUser(
                user: $user,
                title: $this->title,
                body: $this->body,
                data: $this->data,
            );
        } catch (\Throwable $exception) {
            if ($this->notificationId !== null) {
                $this->releasePushClaim();
            }

            throw $exception;
        }
    }

    private function claimPushSend(): bool
    {
        return DB::transaction(function (): bool {
            /** @var AppNotification|null $notification */
            $notification = AppNotification::query()
                ->lockForUpdate()
                ->whereKey($this->notificationId)
                ->first();

            if ($notification === null) {
                return false;
            }

            $data = $notification->data ?? [];

            if (($data['push_sent'] ?? false) === true) {
                return false;
            }

            $data['push_sent'] = true;
            $notification->update(['data' => $data]);

            return true;
        });
    }

    private function releasePushClaim(): void
    {
        DB::transaction(function (): void {
            /** @var AppNotification|null $notification */
            $notification = AppNotification::query()
                ->lockForUpdate()
                ->whereKey($this->notificationId)
                ->first();

            if ($notification === null) {
                return;
            }

            $data = $notification->data ?? [];
            unset($data['push_sent']);
            $notification->update(['data' => $data]);
        });
    }
}
