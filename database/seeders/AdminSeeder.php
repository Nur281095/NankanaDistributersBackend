<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => 'admin@nankanadistributors.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => AdminRole::Admin,
                'status' => AdminStatus::Active,
            ],
        );
    }
}
