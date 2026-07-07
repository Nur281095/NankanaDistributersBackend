<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\GuestCustomer;

class GuestCustomerPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, GuestCustomer $guestCustomer): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, GuestCustomer $guestCustomer): bool
    {
        return false;
    }

    public function delete(Admin $admin, GuestCustomer $guestCustomer): bool
    {
        return false;
    }
}
