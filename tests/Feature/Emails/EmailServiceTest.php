<?php

use App\Enums\AdminStatus;
use App\Enums\CatalogStatus;
use App\Enums\EmailLogStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\SendTemplatedEmailJob;
use App\Mail\TemplatedMail;
use App\Models\Admin;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use App\Services\EmailService;
use App\Support\EmailPlaceholderBuilder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
    ]);
});

describe('EmailPlaceholderBuilder', function (): void {
    it('renders template placeholders', function (): void {
        $rendered = EmailPlaceholderBuilder::render(
            'Hello {customer_name}, order {order_number} total {order_total}.',
            [
                'customer_name' => 'Ali Khan',
                'order_number' => 'ORD-1001',
                'order_total' => 'PKR 1,500.00',
            ],
        );

        expect($rendered)->toBe('Hello Ali Khan, order ORD-1001 total PKR 1,500.00.');
    });

    it('builds order placeholders from an order model', function (): void {
        $order = Order::query()->create([
            'order_number' => 'ORD-2002',
            'user_id' => User::factory()->create()->id,
            'is_guest' => false,
            'customer_name' => 'Sara Ahmed',
            'customer_phone' => '03001234567',
            'customer_email' => 'sara@example.com',
            'delivery_address' => 'House 12',
            'city' => 'Lahore',
            'area' => 'Model Town',
            'subtotal' => 1000,
            'delivery_charges' => 150,
            'discount_amount' => 0,
            'grand_total' => 1150,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => OrderPaymentStatus::CodPending,
            'order_status' => OrderStatus::Received,
        ]);

        $placeholders = EmailPlaceholderBuilder::forOrder($order);

        expect($placeholders['customer_name'])->toBe('Sara Ahmed');
        expect($placeholders['order_number'])->toBe('ORD-2002');
        expect($placeholders['payment_method'])->toBe('Cash on Delivery');
        expect($placeholders['delivery_address'])->toBe('House 12, Model Town, Lahore');
    });
});

describe('EmailService', function (): void {
    it('queues a templated email and dispatches the send job', function (): void {
        Queue::fake();

        $emailLog = app(EmailService::class)->queue(
            templateSlug: 'order_confirmation',
            recipient: 'customer@example.com',
            placeholders: [
                'customer_name' => 'Ali Khan',
                'order_number' => 'ORD-1001',
                'order_total' => 'PKR 1,500.00',
                'payment_method' => 'Cash on Delivery',
            ],
            referenceType: 'order',
            referenceId: 10,
        );

        expect($emailLog)->not->toBeNull();
        expect($emailLog?->status)->toBe(EmailLogStatus::Queued);
        expect($emailLog?->subject)->toBe('Order ORD-1001 confirmed');

        Queue::assertPushed(SendTemplatedEmailJob::class, function (SendTemplatedEmailJob $job) use ($emailLog): bool {
            return $job->emailLogId === $emailLog?->id;
        });
    });

    it('sends mail and marks the email log as sent', function (): void {
        Mail::fake();

        $emailLog = EmailLog::query()->create([
            'recipient' => 'customer@example.com',
            'subject' => 'Test subject',
            'body' => 'Test body',
            'status' => EmailLogStatus::Queued,
        ]);

        app(EmailService::class)->send($emailLog);

        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $mail): bool {
            return $mail->mailSubject === 'Test subject'
                && $mail->mailBody === 'Test body';
        });

        expect($emailLog->fresh()->status)->toBe(EmailLogStatus::Sent);
        expect($emailLog->fresh()->sent_at)->not->toBeNull();
    });

    it('leaves the log queued when mail delivery throws so the job can retry', function (): void {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $emailLog = EmailLog::query()->create([
            'recipient' => 'customer@example.com',
            'subject' => 'Test subject',
            'body' => 'Test body',
            'status' => EmailLogStatus::Queued,
        ]);

        expect(fn () => app(EmailService::class)->send($emailLog))
            ->toThrow(RuntimeException::class, 'SMTP unavailable');

        expect($emailLog->fresh()->status)->toBe(EmailLogStatus::Queued);
    });

    it('records a failed log when the template is inactive', function (): void {
        Queue::fake();

        $template = EmailTemplate::query()->where('slug', 'order_confirmation')->firstOrFail();
        $template->update(['status' => CatalogStatus::Inactive]);

        $emailLog = app(EmailService::class)->queue(
            templateSlug: 'order_confirmation',
            recipient: 'customer@example.com',
            placeholders: [
                'customer_name' => 'Ali Khan',
                'order_number' => 'ORD-1001',
                'order_total' => 'PKR 1,500.00',
                'payment_method' => 'Cash on Delivery',
            ],
        );

        expect($emailLog?->status)->toBe(EmailLogStatus::Failed);
        expect($emailLog?->error_message)->toContain('inactive');

        Queue::assertNothingPushed();
    });

    it('does not queue invalid recipients', function (): void {
        Queue::fake();

        $emailLog = app(EmailService::class)->queue(
            templateSlug: 'order_confirmation',
            recipient: 'not-an-email',
            placeholders: [
                'customer_name' => 'Ali Khan',
                'order_number' => 'ORD-1001',
                'order_total' => 'PKR 1,500.00',
                'payment_method' => 'Cash on Delivery',
            ],
        );

        expect($emailLog)->toBeNull();
        Queue::assertNothingPushed();
    });
});

describe('SendTemplatedEmailJob failure handling', function (): void {
    it('marks the email log failed after the job exhausts retries', function (): void {
        $emailLog = EmailLog::query()->create([
            'recipient' => 'customer@example.com',
            'subject' => 'Test subject',
            'body' => 'Test body',
            'status' => EmailLogStatus::Queued,
        ]);

        $job = new SendTemplatedEmailJob($emailLog->id);
        $job->failed(new RuntimeException('Permanent SMTP failure'));

        expect($emailLog->fresh()->status)->toBe(EmailLogStatus::Failed);
        expect($emailLog->fresh()->error_message)->toBe('Permanent SMTP failure');
    });
});

describe('Password reset email dispatch', function (): void {
    it('queues a password reset email when the user has an email address', function (): void {
        Queue::fake();

        $user = User::factory()->create([
            'phone' => '03007778888',
            'email' => 'reset@example.com',
        ]);

        app(PasswordResetService::class)->sendResetLink($user->phone);

        Queue::assertPushed(SendTemplatedEmailJob::class);

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'reset@example.com',
            'status' => EmailLogStatus::Queued->value,
            'reference_type' => 'user',
            'reference_id' => $user->id,
        ]);
    });
});

describe('Email log admin policy', function (): void {
    it('allows active admins to view email logs but not mutate them', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
        $emailLog = EmailLog::query()->create([
            'recipient' => 'customer@example.com',
            'subject' => 'Test',
            'body' => 'Body',
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
        ]);

        expect(Gate::forUser($admin)->allows('viewAny', EmailLog::class))->toBeTrue();
        expect(Gate::forUser($admin)->allows('view', $emailLog))->toBeTrue();
        expect(Gate::forUser($admin)->allows('create', EmailLog::class))->toBeFalse();
        expect(Gate::forUser($admin)->allows('update', $emailLog))->toBeFalse();
        expect(Gate::forUser($admin)->allows('delete', $emailLog))->toBeFalse();
    });

    it('denies inactive admins access to email logs', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
        $admin->update(['status' => AdminStatus::Inactive]);

        expect(Gate::forUser($admin)->allows('viewAny', EmailLog::class))->toBeFalse();
    });
});
