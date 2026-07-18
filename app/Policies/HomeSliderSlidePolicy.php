<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\HomeSliderSlide;

class HomeSliderSlidePolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, HomeSliderSlide $homeSliderSlide): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, HomeSliderSlide $homeSliderSlide): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, HomeSliderSlide $homeSliderSlide): bool
    {
        return $this->adminIsActive($admin);
    }
}
