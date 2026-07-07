<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class UserPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, User $user): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, User $user): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, User $user): bool
    {
        return false;
    }

    public function restore(Admin $admin, User $user): bool
    {
        return false;
    }

    public function forceDelete(Admin $admin, User $user): bool
    {
        return false;
    }
}
