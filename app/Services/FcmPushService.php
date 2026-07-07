<?php

namespace App\Services;

use App\Enums\TokenStatus;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    private const LEGACY_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /**
     * @var list<string>
     */
    private const INVALID_TOKEN_ERRORS = [
        'InvalidRegistration',
        'NotRegistered',
        'MismatchSenderId',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('status', TokenStatus::Active)
            ->pluck('device_token');

        $successCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'key='.config('services.fcm.server_key'),
            'Content-Type' => 'application/json',
        ])->post(self::LEGACY_ENDPOINT, [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $this->normalizeData($data),
        ]);

        if (! $response->successful()) {
            Log::warning('FCM push request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();
        $results = $payload['results'] ?? [];
        $result = is_array($results[0] ?? null) ? $results[0] : [];

        if (isset($result['message_id'])) {
            return true;
        }

        $error = is_string($result['error'] ?? null) ? $result['error'] : null;

        if ($error !== null && in_array($error, self::INVALID_TOKEN_ERRORS, true)) {
            $this->deactivateToken($deviceToken);
        }

        if ($error !== null) {
            Log::warning('FCM push delivery failed.', [
                'error' => $error,
                'token' => $deviceToken,
            ]);
        }

        return false;
    }

    public function isEnabled(): bool
    {
        if (! (bool) config('services.fcm.enabled')) {
            return false;
        }

        $serverKey = config('services.fcm.server_key');

        return is_string($serverKey) && $serverKey !== '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = (string) $value;

                continue;
            }

            $encoded = json_encode($value);

            $normalized[$key] = $encoded === false ? '' : $encoded;
        }

        return $normalized;
    }

    private function deactivateToken(string $deviceToken): void
    {
        DeviceToken::query()
            ->where('device_token', $deviceToken)
            ->update(['status' => TokenStatus::Inactive]);
    }
}
