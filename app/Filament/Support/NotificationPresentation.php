<?php

namespace App\Filament\Support;

use App\Enums\NotificationType;

class NotificationPresentation
{
    public static function typeColor(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::Order => 'info',
            NotificationType::Offer => 'success',
            NotificationType::Admin => 'warning',
            NotificationType::System => 'gray',
            NotificationType::LowStock => 'danger',
            NotificationType::Payment => 'primary',
        };
    }
}
