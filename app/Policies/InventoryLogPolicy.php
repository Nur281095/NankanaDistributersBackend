<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\InventoryLog;

class InventoryLogPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, InventoryLog $inventoryLog): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, InventoryLog $inventoryLog): bool
    {
        return false;
    }

    public function delete(Admin $admin, InventoryLog $inventoryLog): bool
    {
        return false;
    }
}
