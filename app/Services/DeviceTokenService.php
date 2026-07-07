<?php

namespace App\Services;

use App\Enums\DevicePlatform;
use App\Enums\TokenStatus;
use App\Exceptions\BusinessException;
use App\Models\DeviceToken;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenService
{
    public function store(User $user, string $deviceToken, DevicePlatform $platform): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['device_token' => $deviceToken],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'status' => TokenStatus::Active,
            ],
        );
    }

    public function remove(User $user, string $deviceToken): void
    {
        $token = DeviceToken::query()
            ->where('device_token', $deviceToken)
            ->where('user_id', $user->id)
            ->first();

        if ($token === null) {
            throw new BusinessException(
                'Device token not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        $token->delete();
    }
}
