<?php

use App\Enums\AdminStatus;
use App\Enums\NotificationType;
use App\Jobs\SendAppNotificationJob;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminSeeder::class);
});

describe('NotificationService', function (): void {
    it('creates an in-app notification for a customer', function (): void {
        $user = User::factory()->create();

        $notification = app(NotificationService::class)->createForUser(
            user: $user,
            title: 'Order update',
            message: 'Your order has been packed.',
            type: NotificationType::Order,
            data: ['order_id' => 12],
            referenceType: 'order',
            referenceId: 12,
        );

        expect($notification->user_id)->toBe($user->id);
        expect($notification->admin_id)->toBeNull();
        expect($notification->type)->toBe(NotificationType::Order);
        expect($notification->is_read)->toBeFalse();
        expect($notification->data)->toBe(['order_id' => 12]);
    });

    it('creates an in-app notification for an admin', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();

        $notification = app(NotificationService::class)->createForAdmin(
            admin: $admin,
            title: 'New order',
            message: 'Order ORD-1001 was placed.',
            type: NotificationType::Admin,
            referenceType: 'order',
            referenceId: 5,
        );

        expect($notification->admin_id)->toBe($admin->id);
        expect($notification->user_id)->toBeNull();
    });

    it('queues notification creation through the job', function (): void {
        Queue::fake();

        $user = User::factory()->create();

        app(NotificationService::class)->queueForUser(
            user: $user,
            title: 'Payment received',
            message: 'Your payment was successful.',
            type: NotificationType::Payment,
        );

        Queue::assertPushed(SendAppNotificationJob::class, function (SendAppNotificationJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->type === NotificationType::Payment;
        });
    });

    it('marks a notification as read for the owning user', function (): void {
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $notification = $service->createForUser(
            user: $user,
            title: 'Order update',
            message: 'Your order has been delivered.',
            type: NotificationType::Order,
        );

        $updated = $service->markAsRead($user, $notification);

        expect($updated->is_read)->toBeTrue();
        expect($updated->read_at)->not->toBeNull();
    });

    it('marks all unread notifications as read for a user', function (): void {
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $service->createForUser($user, 'One', 'First message', NotificationType::System);
        $service->createForUser($user, 'Two', 'Second message', NotificationType::System);

        $updatedCount = $service->markAllAsReadForUser($user);

        expect($updatedCount)->toBe(2);
        expect($service->unreadCountForUser($user))->toBe(0);
    });

    it('counts unread notifications for a user', function (): void {
        $user = User::factory()->create();
        $service = app(NotificationService::class);

        $readNotification = $service->createForUser($user, 'Read', 'Already read', NotificationType::System);
        $service->markAsRead($user, $readNotification);
        $service->createForUser($user, 'Unread', 'Still unread', NotificationType::System);

        expect($service->unreadCountForUser($user))->toBe(1);
    });
});

describe('SendAppNotificationJob', function (): void {
    it('persists a queued customer notification', function (): void {
        $user = User::factory()->create();

        $job = new SendAppNotificationJob(
            userId: $user->id,
            adminId: null,
            title: 'Order placed',
            message: 'Your order ORD-1001 was placed.',
            type: NotificationType::Order,
            data: ['order_id' => 7],
            referenceType: 'order',
            referenceId: 7,
        );

        $job->handle(app(NotificationService::class));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'title' => 'Order placed',
            'type' => NotificationType::Order->value,
            'reference_id' => 7,
        ]);
    });
});

describe('App notification policy', function (): void {
    it('allows customers to update only their own notifications', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $notification = AppNotification::query()->create([
            'user_id' => $owner->id,
            'title' => 'Order update',
            'message' => 'Packed',
            'type' => NotificationType::Order,
        ]);

        expect(Gate::forUser($owner)->allows('update', $notification))->toBeTrue();
        expect(Gate::forUser($other)->allows('update', $notification))->toBeFalse();
    });

    it('allows admins to mark admin-targeted notifications as read', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();

        $adminNotification = AppNotification::query()->create([
            'admin_id' => $admin->id,
            'title' => 'New order',
            'message' => 'Order received',
            'type' => NotificationType::Admin,
        ]);

        $customerNotification = AppNotification::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Order update',
            'message' => 'Packed',
            'type' => NotificationType::Order,
        ]);

        expect(Gate::forUser($admin)->allows('update', $adminNotification))->toBeTrue();
        expect(Gate::forUser($admin)->allows('update', $customerNotification))->toBeFalse();
    });

    it('denies inactive admins notification access', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
        $admin->update(['status' => AdminStatus::Inactive]);

        expect(Gate::forUser($admin)->allows('viewAny', AppNotification::class))->toBeFalse();
    });
});
