<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Company;

class CompanyPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, Company $company): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, Company $company): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, Company $company): bool
    {
        return $this->adminIsActive($admin);
    }

    public function restore(Admin $admin, Company $company): bool
    {
        return $this->adminIsActive($admin);
    }

    public function forceDelete(Admin $admin, Company $company): bool
    {
        return $this->adminIsActive($admin);
    }
}
