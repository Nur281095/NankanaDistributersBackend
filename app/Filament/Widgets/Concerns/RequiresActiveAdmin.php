<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\AdminStatus;
use App\Models\Admin;

trait RequiresActiveAdmin
{
    public static function canView(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin
            && $admin->status === AdminStatus::Active;
    }
}
