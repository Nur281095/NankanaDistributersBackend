<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\ChangedByType;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable implements CanResetPasswordContract, FilamentUser
{
    use CanResetPassword;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::forceDeleting(function (Admin $admin): void {
            OrderStatusLog::query()
                ->where('changed_by_type', ChangedByType::Admin)
                ->where('changed_by', $admin->id)
                ->update(['changed_by' => null]);
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === AdminStatus::Active;
    }

    /**
     * @return HasMany<InventoryLog, $this>
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * @return HasMany<AppNotification, $this>
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'status' => AdminStatus::class,
            'password' => 'hashed',
        ];
    }
}
