<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\OfferTarget;

class OfferTargetPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, OfferTarget $offerTarget): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, OfferTarget $offerTarget): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, OfferTarget $offerTarget): bool
    {
        return $this->adminIsActive($admin);
    }
}
