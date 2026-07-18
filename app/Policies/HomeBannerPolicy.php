<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\HomeBanner;

class HomeBannerPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, HomeBanner $homeBanner): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, HomeBanner $homeBanner): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, HomeBanner $homeBanner): bool
    {
        return $this->adminIsActive($admin);
    }
}
