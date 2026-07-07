<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\EmailTemplate;
use App\Models\Offer;
use App\Models\Setting;

class OfferPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Offer $offer): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, Offer $offer): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Offer $offer): bool
    {
        return $this->adminIsActive($admin);
    }

    public function restore(Admin $admin, Offer $offer): bool
    {
        return $this->adminIsActive($admin);
    }

    public function forceDelete(Admin $admin, Offer $offer): bool
    {
        return $this->adminIsActive($admin);
    }
}
