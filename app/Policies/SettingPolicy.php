<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Setting;

class SettingPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Setting $setting): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Setting $setting): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Setting $setting): bool
    {
        return false;
    }
}
