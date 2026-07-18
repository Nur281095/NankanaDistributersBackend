<?php

use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('Auth API', function (): void {
    it('registers a new customer', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali Khan',
            'phone' => '03001234567',
            'email' => 'ali@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', '03001234567')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'phone', 'email'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '03001234567',
            'status' => UserStatus::Active->value,
        ]);
    });

    it('logs in with valid credentials', function (): void {
        $user = User::factory()->create([
            'phone' => '03007654321',
            'password' => Hash::make('password1'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '03007654321',
            'password' => 'password1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    });

    it('rejects login for blocked users', function (): void {
        User::factory()->create([
            'phone' => '03001112222',
            'password' => Hash::make('password1'),
            'status' => UserStatus::Blocked,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '03001112222',
            'password' => 'password1',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    });

    it('returns profile for authenticated users', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $response = $this->getJson('/api/v1/auth/profile', authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);
    });

    it('updates profile', function (): void {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'status' => UserStatus::Active,
        ]);

        $response = $this->patchJson('/api/v1/auth/profile', [
            'name' => 'New Name',
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    });

    it('logs out and revokes token', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $headers = authApiHeaders($user);
        $tokenId = $user->tokens()->first()?->id;

        $this->postJson('/api/v1/auth/logout', [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        Auth::forgetGuards();

        $this->getJson('/api/v1/auth/profile', $headers)
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    });

    it('accepts forgot password without revealing account existence', function (): void {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '03009998888',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    });

    it('resets password with a valid token', function (): void {
        $user = User::factory()->create([
            'phone' => '03005556666',
            'email' => null,
            'password' => Hash::make('oldpassword1'),
            'status' => UserStatus::Active,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '03005556666',
        ])->assertOk();

        $token = 'test-reset-token-1234567890123456789012345678901234567890123456789012345678901234';

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->phone],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ],
        );

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '03005556666',
            'token' => $token,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '03005556666',
            'password' => 'newpassword1',
        ])->assertOk();
    });

    it('rejects inactive authenticated users via middleware', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $headers = authApiHeaders($user);

        $user->update(['status' => UserStatus::Inactive]);

        $this->getJson('/api/v1/auth/profile', $headers)
            ->assertForbidden();
    });
});

describe('Address API', function (): void {
    it('creates and lists addresses', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $headers = authApiHeaders($user);

        $create = $this->postJson('/api/v1/addresses', [
            'label' => 'Home',
            'name' => 'Ali Khan',
            'phone' => '03001234567',
            'address' => '123 Main Street',
            'city' => 'Lahore',
            'area' => 'Gulberg',
            'is_default' => true,
        ], $headers);

        $create->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->getJson('/api/v1/addresses', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('prevents accessing another users address', function (): void {
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $other = User::factory()->create(['status' => UserStatus::Active]);

        $address = CustomerAddress::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner',
            'phone' => '03001234567',
            'address' => 'Secret address',
            'is_default' => true,
        ]);

        $this->getJson('/api/v1/addresses/'.$address->id, authApiHeaders($other))
            ->assertForbidden();
    });

    it('forbids updating another users address', function (): void {
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $other = User::factory()->create(['status' => UserStatus::Active]);

        $address = CustomerAddress::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner',
            'phone' => '03001234567',
            'address' => 'Secret address',
            'is_default' => true,
        ]);

        $this->patchJson('/api/v1/addresses/'.$address->id, [
            'name' => 'Intruder',
            'phone' => '03001234567',
            'address' => 'Hacked address',
        ], authApiHeaders($other))->assertForbidden();
    });

    it('forbids deleting another users address', function (): void {
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $other = User::factory()->create(['status' => UserStatus::Active]);

        $address = CustomerAddress::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owner',
            'phone' => '03001234567',
            'address' => 'Secret address',
            'is_default' => true,
        ]);

        $this->deleteJson('/api/v1/addresses/'.$address->id, [], authApiHeaders($other))
            ->assertForbidden();

        expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeTrue();
    });

    it('deletes an address and promotes another default', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $headers = authApiHeaders($user);

        $first = CustomerAddress::query()->create([
            'user_id' => $user->id,
            'name' => 'Ali',
            'phone' => '03001234567',
            'address' => 'First',
            'is_default' => true,
        ]);

        $second = CustomerAddress::query()->create([
            'user_id' => $user->id,
            'name' => 'Ali',
            'phone' => '03001234567',
            'address' => 'Second',
            'is_default' => false,
        ]);

        $this->deleteJson('/api/v1/addresses/'.$first->id, [], $headers)
            ->assertOk();

        expect($second->fresh()->is_default)->toBeTrue();
    });
});
