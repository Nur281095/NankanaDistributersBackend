<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Admin;
use App\Models\Order;
use App\Support\EmailPlaceholderBuilder;

class PaymentNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailService $emailService,
    ) {}

    public function notifyPaymentSuccess(Order $order): void
    {
        $this->notifyCustomer(
            order: $order,
            title: 'Payment received',
            message: 'Payment for order '.$order->order_number.' was successful.',
            templateSlug: 'payment_success',
        );
    }

    public function notifyPaymentFailure(Order $order, ?string $reason = null): void
    {
        $message = 'Payment for order '.$order->order_number.' could not be completed. Please try again.';

        if ($reason !== null && $reason !== '') {
            $message .= ' Reason: '.$reason;
        }

        $this->notifyCustomer(
            order: $order,
            title: 'Payment failed',
            message: $message,
            templateSlug: 'payment_failed',
        );
    }

    private function notifyCustomer(
        Order $order,
        string $title,
        string $message,
        string $templateSlug,
    ): void {
        if ($order->user_id !== null) {
            $user = $order->user;

            if ($user !== null) {
                $this->notificationService->queueForUser(
                    user: $user,
                    title: $title,
                    message: $message,
                    type: NotificationType::Payment,
                    data: [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    referenceType: 'order',
                    referenceId: $order->id,
                );
            }
        }

        $recipient = $order->customer_email;

        if ($recipient === null || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->emailService->queue(
            templateSlug: $templateSlug,
            recipient: $recipient,
            placeholders: EmailPlaceholderBuilder::forOrder($order),
            referenceType: 'order',
            referenceId: $order->id,
        );
    }
}
