<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Product;

class ProductPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }

    public function restore(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }

    public function forceDelete(Admin $admin, Product $product): bool
    {
        return $this->adminIsActive($admin);
    }
}
