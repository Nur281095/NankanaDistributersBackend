<?php

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Notifications API', function (): void {
    it('lists the authenticated users notifications with pagination', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $other = User::factory()->create(['status' => UserStatus::Active]);
        $service = app(NotificationService::class);

        $service->createForUser($user, 'Order placed', 'Your order was placed.', NotificationType::Order);
        $service->createForUser($user, 'Offer alert', 'A new offer is live.', NotificationType::Offer);
        $service->createForUser($other, 'Hidden', 'Should not appear', NotificationType::System);

        $response = $this->getJson('/api/v1/notifications?per_page=10', authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'type',
                        'data',
                        'reference_type',
                        'reference_id',
                        'is_read',
                        'read_at',
                        'created_at',
                    ],
                ],
            ]);
    });

    it('filters notifications by read status', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $service = app(NotificationService::class);

        $readNotification = $service->createForUser($user, 'Read', 'Already read', NotificationType::System);
        $service->markAsRead($user, $readNotification);
        $service->createForUser($user, 'Unread', 'Still unread', NotificationType::System);

        $unreadResponse = $this->getJson('/api/v1/notifications?is_read=0', authApiHeaders($user));
        $readResponse = $this->getJson('/api/v1/notifications?is_read=1', authApiHeaders($user));

        $unreadResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Unread');
        $readResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Read');
    });

    it('returns unread notification count', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $service = app(NotificationService::class);

        $readNotification = $service->createForUser($user, 'Read', 'Already read', NotificationType::System);
        $service->markAsRead($user, $readNotification);
        $service->createForUser($user, 'Unread one', 'First unread', NotificationType::System);
        $service->createForUser($user, 'Unread two', 'Second unread', NotificationType::System);

        $response = $this->getJson('/api/v1/notifications/unread-count', authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 2);
    });

    it('marks a single notification as read', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $notification = app(NotificationService::class)->createForUser(
            user: $user,
            title: 'Order update',
            message: 'Your order has been packed.',
            type: NotificationType::Order,
            data: ['order_id' => 15],
            referenceType: 'order',
            referenceId: 15,
        );

        $response = $this->patchJson(
            "/api/v1/notifications/{$notification->id}/read",
            [],
            authApiHeaders($user),
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.reference_id', 15);

        expect($notification->fresh()->is_read)->toBeTrue();
    });

    it('marks all notifications as read', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $service = app(NotificationService::class);

        $service->createForUser($user, 'One', 'First', NotificationType::System);
        $service->createForUser($user, 'Two', 'Second', NotificationType::System);

        $response = $this->postJson('/api/v1/notifications/read-all', [], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 2);

        expect($service->unreadCountForUser($user))->toBe(0);
    });

    it('prevents accessing another users notification', function (): void {
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $other = User::factory()->create(['status' => UserStatus::Active]);

        $notification = AppNotification::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'message' => 'Owner only',
            'type' => NotificationType::Order,
        ]);

        $this->patchJson(
            "/api/v1/notifications/{$notification->id}/read",
            [],
            authApiHeaders($other),
        )->assertNotFound();
    });

    it('requires authentication', function (): void {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    });
});
