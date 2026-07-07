<?php

use App\Enums\DevicePlatform;
use App\Enums\TokenStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmPushService;
use App\Services\NotificationService;
use App\Enums\NotificationType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.fcm.enabled' => true,
        'services.fcm.server_key' => 'test-fcm-server-key',
    ]);
});

describe('FcmPushService', function (): void {
    it('sends a push notification to active device tokens', function (): void {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 1,
                'failure' => 0,
                'results' => [
                    ['message_id' => 'msg-123'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'device_token' => 'device-token-abc',
            'platform' => DevicePlatform::Android,
            'status' => TokenStatus::Active,
        ]);

        $sentCount = app(FcmPushService::class)->sendToUser(
            user: $user,
            title: 'Order update',
            body: 'Your order has been packed.',
            data: [
                'type' => NotificationType::Order->value,
                'reference_id' => 15,
            ],
        );

        expect($sentCount)->toBe(1);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://fcm.googleapis.com/fcm/send'
                && ($payload['to'] ?? null) === 'device-token-abc'
                && ($payload['notification']['title'] ?? null) === 'Order update'
                && ($payload['data']['reference_id'] ?? null) === '15';
        });
    });

    it('deactivates invalid device tokens returned by fcm', function (): void {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'success' => 0,
                'failure' => 1,
                'results' => [
                    ['error' => 'NotRegistered'],
                ],
            ], 200),
        ]);

        $token = DeviceToken::query()->create([
            'user_id' => User::factory()->create()->id,
            'device_token' => 'expired-device-token',
            'platform' => DevicePlatform::Ios,
            'status' => TokenStatus::Active,
        ]);

        $sent = app(FcmPushService::class)->sendToToken(
            deviceToken: 'expired-device-token',
            title: 'Test',
            body: 'Body',
        );

        expect($sent)->toBeFalse();
        expect($token->fresh()->status)->toBe(TokenStatus::Inactive);
    });

    it('skips delivery when fcm is disabled', function (): void {
        config(['services.fcm.enabled' => false]);

        Http::fake();

        $user = User::factory()->create();

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'device_token' => 'device-token-abc',
            'platform' => DevicePlatform::Android,
            'status' => TokenStatus::Active,
        ]);

        $sentCount = app(FcmPushService::class)->sendToUser(
            user: $user,
            title: 'Order update',
            body: 'Your order has been packed.',
        );

        expect($sentCount)->toBe(0);
        Http::assertNothingSent();
    });
});

describe('Notification push dispatch', function (): void {
    it('queues a push job when a customer notification is created', function (): void {
        Queue::fake();

        $user = User::factory()->create();

        app(NotificationService::class)->createForUser(
            user: $user,
            title: 'Order update',
            message: 'Your order has been packed.',
            type: NotificationType::Order,
            data: ['order_id' => 9],
            referenceType: 'order',
            referenceId: 9,
        );

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->title === 'Order update'
                && $job->body === 'Your order has been packed.'
                && ($job->data['type'] ?? null) === NotificationType::Order->value
                && ($job->data['order_id'] ?? null) === 9
                && ($job->data['reference_id'] ?? null) === 9;
        });
    });
});
