<?php

namespace App\Filament\Support;

use App\Enums\EmailLogStatus;

class EmailLogPresentation
{
    public static function statusColor(EmailLogStatus $status): string
    {
        return match ($status) {
            EmailLogStatus::Sent => 'success',
            EmailLogStatus::Queued => 'warning',
            EmailLogStatus::Failed => 'danger',
        };
    }
}
