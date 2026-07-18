<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\HomeSection;
use App\Models\Product;

class HomeSectionPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, HomeSection $homeSection): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, HomeSection $homeSection): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, HomeSection $homeSection): bool
    {
        return $this->adminIsActive($admin);
    }

    public function restore(Admin $admin, HomeSection $homeSection): bool
    {
        return $this->adminIsActive($admin);
    }

    public function forceDelete(Admin $admin, HomeSection $homeSection): bool
    {
        return $this->adminIsActive($admin);
    }

    public function reorder(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function attachAnyProduct(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function attachProduct(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }

    public function detachProduct(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }
}
