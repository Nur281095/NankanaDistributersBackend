<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id;
    }

    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id;
    }

    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->id === $customerAddress->user_id;
    }
}
