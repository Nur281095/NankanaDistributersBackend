<?php

use App\Enums\AdminStatus;
use App\Enums\InventoryLogType;
use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\CustomerAddress;
use App\Models\GuestCustomer;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Support\Facades\Gate;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        DemoCatalogSeeder::class,
    ]);

    $this->admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
    $this->product = Product::query()->firstOrFail();
});

describe('InventoryService adjustStock', function (): void {
    it('adds stock and writes an inventory log', function (): void {
        $startingStock = $this->product->stock_quantity;

        $log = app(InventoryService::class)->adjustStock(
            product: $this->product,
            quantityChange: 5,
            admin: $this->admin,
            type: InventoryLogType::Added,
            note: 'Restocked from supplier',
        );

        $this->product->refresh();

        expect($this->product->stock_quantity)->toBe($startingStock + 5);
        expect($log->type)->toBe(InventoryLogType::Added);
        expect($log->old_quantity)->toBe($startingStock);
        expect($log->new_quantity)->toBe($startingStock + 5);
        expect($log->quantity_difference)->toBe(5);
        expect($log->admin_id)->toBe($this->admin->id);
        expect($log->note)->toBe('Restocked from supplier');
    });

    it('removes stock when enough inventory is available', function (): void {
        $this->product->update(['stock_quantity' => 20]);

        $log = app(InventoryService::class)->adjustStock(
            product: $this->product,
            quantityChange: -3,
            admin: $this->admin,
            type: InventoryLogType::Damaged,
        );

        $this->product->refresh();

        expect($this->product->stock_quantity)->toBe(17);
        expect($log->quantity_difference)->toBe(-3);
        expect($log->type)->toBe(InventoryLogType::Damaged);
    });

    it('prevents stock from going negative', function (): void {
        $this->product->update(['stock_quantity' => 2]);

        expect(fn () => app(InventoryService::class)->adjustStock(
            product: $this->product,
            quantityChange: -5,
            admin: $this->admin,
        ))->toThrow(BusinessException::class, 'Insufficient stock');

        expect($this->product->fresh()->stock_quantity)->toBe(2);
        expect(InventoryLog::query()->count())->toBe(0);
    });

    it('rejects zero quantity adjustments', function (): void {
        expect(fn () => app(InventoryService::class)->adjustStock(
            product: $this->product,
            quantityChange: 0,
            admin: $this->admin,
        ))->toThrow(BusinessException::class, 'cannot be zero');
    });
});

describe('Admin inventory and customer policies', function (): void {
    it('allows active admins to view inventory logs and customers', function (): void {
        $user = User::factory()->create();
        $guest = GuestCustomer::query()->create([
            'name' => 'Guest Shopper',
            'phone' => '03001234567',
            'email' => 'guest@example.com',
            'address' => '123 Main Street',
            'city' => 'Lahore',
        ]);
        $log = InventoryLog::query()->create([
            'product_id' => $this->product->id,
            'admin_id' => $this->admin->id,
            'type' => InventoryLogType::ManualAdjustment,
            'old_quantity' => 10,
            'new_quantity' => 15,
            'quantity_difference' => 5,
            'reference_type' => 'manual_adjustment',
            'note' => 'Test log',
        ]);

        expect(Gate::forUser($this->admin)->allows('viewAny', InventoryLog::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $log))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', InventoryLog::class))->toBeFalse();

        expect(Gate::forUser($this->admin)->allows('viewAny', User::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $user))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $user))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', User::class))->toBeFalse();

        expect(Gate::forUser($this->admin)->allows('viewAny', GuestCustomer::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $guest))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $guest))->toBeFalse();
    });

    it('denies inactive admins access to inventory and customer resources', function (): void {
        $this->admin->update(['status' => AdminStatus::Inactive]);
        $user = User::factory()->create();
        $guest = GuestCustomer::query()->create([
            'name' => 'Guest Shopper',
            'phone' => '03007654321',
            'address' => '456 Side Street',
        ]);

        expect(Gate::forUser($this->admin)->allows('viewAny', InventoryLog::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', User::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('update', $user))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', GuestCustomer::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('view', $guest))->toBeFalse();
    });
});

describe('Customer admin data', function (): void {
    it('stores customer addresses for admin review', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        CustomerAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => $user->name,
            'phone' => $user->phone,
            'address' => 'House 12, Block A',
            'city' => 'Karachi',
            'area' => 'Clifton',
            'is_default' => true,
        ]);

        expect($user->addresses()->count())->toBe(1);
        expect($user->fresh()->status)->toBe(UserStatus::Active);
    });
});
