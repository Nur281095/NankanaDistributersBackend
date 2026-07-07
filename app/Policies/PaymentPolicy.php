<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Payment;

class PaymentPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Payment $payment): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Payment $payment): bool
    {
        return false;
    }

    public function delete(Admin $admin, Payment $payment): bool
    {
        return false;
    }
}
