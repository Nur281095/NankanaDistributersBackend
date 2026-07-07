<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Brand;

class BrandPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Brand $brand): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, Brand $brand): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Brand $brand): bool
    {
        return $this->adminIsActive($admin);
    }

    public function restore(Admin $admin, Brand $brand): bool
    {
        return $this->adminIsActive($admin);
    }

    public function forceDelete(Admin $admin, Brand $brand): bool
    {
        return $this->adminIsActive($admin);
    }
}
