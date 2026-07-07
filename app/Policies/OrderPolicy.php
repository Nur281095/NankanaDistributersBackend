<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;

class OrderPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin|User $user, Order $order): bool
    {
        if ($user instanceof Admin) {
            return $this->adminIsActive($user);
        }

        return $order->user_id === $user->id;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Order $order): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Order $order): bool
    {
        return false;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
