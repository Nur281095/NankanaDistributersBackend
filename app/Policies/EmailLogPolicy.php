<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\EmailLog;

class EmailLogPolicy extends AdminResourcePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $this->adminIsActive($admin);
    }

    public function view(Admin $admin, EmailLog $emailLog): bool
    {
        return $this->adminIsActive($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, EmailLog $emailLog): bool
    {
        return false;
    }

    public function delete(Admin $admin, EmailLog $emailLog): bool
    {
        return false;
    }
}
