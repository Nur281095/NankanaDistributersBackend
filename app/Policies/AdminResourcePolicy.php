<?php

namespace App\Policies;

use App\Enums\AdminStatus;
use App\Models\Admin;

/**
 * Base policy for Filament admin resources.
 *
 * MVP access control: any active admin can manage admin-panel resources.
 * Extend this class for each model and delegate standard abilities to adminIsActive().
 */
abstract class AdminResourcePolicy
{
    protected function adminIsActive(Admin $admin): bool
    {
        return $admin->status === AdminStatus::Active;
    }

    public function viewDashboard(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }
}
