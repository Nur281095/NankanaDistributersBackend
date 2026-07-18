<?php

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\NotificationType;
use App\Models\Admin;
use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('keeps admin notifications when an admin is force deleted', function (): void {
    $admin = Admin::query()->create([
        'name' => 'Temp Admin',
        'email' => 'temp-admin@example.com',
        'password' => Hash::make('password'),
        'role' => AdminRole::Admin,
        'status' => AdminStatus::Active,
    ]);

    $notification = AppNotification::query()->create([
        'admin_id' => $admin->id,
        'title' => 'Keep me',
        'message' => 'Should survive admin force delete',
        'type' => NotificationType::Admin,
    ]);

    $admin->forceDelete();

    expect(AppNotification::query()->whereKey($notification->id)->exists())->toBeTrue();
    expect($notification->fresh()->admin_id)->toBeNull();
});
