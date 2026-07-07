<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Device\DeleteDeviceTokenRequest;
use App\Http\Requests\Api\Device\StoreDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Services\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly DeviceTokenService $deviceTokenService,
    ) {}

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = $this->deviceTokenService->store(
            $request->user(),
            $request->deviceToken(),
            $request->platform(),
        );

        return $this->success(
            DeviceTokenResource::make($token)->resolve(),
            'Device token saved.',
            Response::HTTP_CREATED,
        );
    }

    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokenService->remove(
            $request->user(),
            $request->deviceToken(),
        );

        return $this->success(null, 'Device token removed.');
    }
}
