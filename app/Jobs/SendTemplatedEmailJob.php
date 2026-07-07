<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTemplatedEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public int $emailLogId,
    ) {}

    public function handle(EmailService $emailService): void
    {
        $emailLog = EmailLog::query()->find($this->emailLogId);

        if ($emailLog === null) {
            return;
        }

        $emailService->send($emailLog);
    }

    public function failed(?Throwable $exception): void
    {
        $emailLog = EmailLog::query()->find($this->emailLogId);

        if ($emailLog === null) {
            return;
        }

        $emailService = app(EmailService::class);
        $message = $exception?->getMessage() ?? 'Email delivery failed after multiple attempts.';

        $emailService->markFailed($emailLog, $message);
    }
}
