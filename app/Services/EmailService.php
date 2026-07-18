<?php

namespace App\Services;

use App\Enums\CatalogStatus;
use App\Enums\EmailLogStatus;
use App\Jobs\SendTemplatedEmailJob;
use App\Mail\TemplatedMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\EmailPlaceholderBuilder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailService
{
    /**
     * Queue a templated email for delivery.
     *
     * @param  array<string, string>  $placeholders
     */
    public function queue(
        string $templateSlug,
        string $recipient,
        array $placeholders = [],
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): ?EmailLog {
        $recipient = trim($recipient);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $template = EmailTemplate::query()->where('slug', $templateSlug)->first();

        if ($template === null) {
            return $this->createFailedLog(
                recipient: $recipient,
                subject: Str::headline(str_replace('_', ' ', $templateSlug)),
                body: null,
                errorMessage: "Email template [{$templateSlug}] was not found.",
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        }

        if ($template->status !== CatalogStatus::Active) {
            return $this->createFailedLog(
                recipient: $recipient,
                subject: $template->subject,
                body: $template->body,
                errorMessage: "Email template [{$templateSlug}] is inactive.",
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        }

        try {
            EmailPlaceholderBuilder::assertPlaceholders(
                $template->subject,
                $template->body,
                $placeholders,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->createFailedLog(
                recipient: $recipient,
                subject: $template->subject,
                body: $template->body,
                errorMessage: $exception->getMessage(),
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        }

        $rendered = $this->renderTemplate($template, $placeholders);

        $emailLog = EmailLog::query()->create([
            'recipient' => $recipient,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => EmailLogStatus::Queued,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        SendTemplatedEmailJob::dispatch($emailLog->id);

        return $emailLog;
    }

    /**
     * Send a queued email log entry.
     */
    public function send(EmailLog $emailLog): void
    {
        if (in_array($emailLog->status, [EmailLogStatus::Sent, EmailLogStatus::Failed], true)) {
            return;
        }

        $claimed = EmailLog::query()
            ->whereKey($emailLog->id)
            ->where('status', EmailLogStatus::Queued)
            ->update([
                'status' => EmailLogStatus::Sending,
                'error_message' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $emailLog->refresh();

        try {
            Mail::to($emailLog->recipient)->send(new TemplatedMail(
                mailSubject: $emailLog->subject,
                mailBody: (string) $emailLog->body,
            ));

            $emailLog->update([
                'status' => EmailLogStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            EmailLog::query()
                ->whereKey($emailLog->id)
                ->where('status', EmailLogStatus::Sending)
                ->update([
                    'status' => EmailLogStatus::Queued,
                ]);

            throw $exception;
        }
    }

    public function markFailed(EmailLog $emailLog, string $errorMessage): void
    {
        if ($emailLog->status === EmailLogStatus::Sent) {
            return;
        }

        $emailLog->update([
            'status' => EmailLogStatus::Failed,
            'error_message' => Str::limit($errorMessage, 1000),
        ]);
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array{subject: string, body: string}
     */
    public function renderTemplate(EmailTemplate $template, array $placeholders): array
    {
        return [
            'subject' => EmailPlaceholderBuilder::render($template->subject, $placeholders),
            'body' => EmailPlaceholderBuilder::render($template->body, $placeholders),
        ];
    }

    private function createFailedLog(
        string $recipient,
        string $subject,
        ?string $body,
        string $errorMessage,
        ?string $referenceType,
        ?int $referenceId,
    ): EmailLog {
        return EmailLog::query()->create([
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailLogStatus::Failed,
            'error_message' => Str::limit($errorMessage, 1000),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
