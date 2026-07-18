<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\User;

class AppNotificationPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin|User $user, AppNotification $appNotification): bool
    {
        if ($user instanceof Admin) {
            return $this->adminIsActive($user);
        }

        return $appNotification->user_id === $user->id;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin|User $user, AppNotification $appNotification): bool
    {
        if ($user instanceof User) {
            return $appNotification->user_id === $user->id;
        }

        return $this->adminIsActive($user)
            && $appNotification->admin_id === $user->id;
    }

    public function delete(Admin $admin, AppNotification $appNotification): bool
    {
        return false;
    }
}
