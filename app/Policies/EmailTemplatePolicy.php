<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\EmailTemplate;

class EmailTemplatePolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, EmailTemplate $emailTemplate): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function update(Admin $admin, EmailTemplate $emailTemplate): bool
    {
        return $this->adminIsActive($admin);
    }

    public function delete(Admin $admin, EmailTemplate $emailTemplate): bool
    {
        return $this->adminIsActive($admin);
    }
}
