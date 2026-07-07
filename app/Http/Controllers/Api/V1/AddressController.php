<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Address\StoreAddressRequest;
use App\Http\Requests\Api\Address\UpdateAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->listForUser($request->user());

        return $this->success(
            CustomerAddressResource::collection($addresses)->resolve(),
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            CustomerAddressResource::make($address)->resolve(),
            'Address saved successfully.',
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('view', $address);

        return $this->success(
            CustomerAddressResource::make($address)->resolve(),
        );
    }

    public function update(UpdateAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $address);

        $address = $this->addressService->update(
            $request->user(),
            $address,
            $request->validated(),
        );

        return $this->success(
            CustomerAddressResource::make($address)->resolve(),
            'Address updated successfully.',
        );
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $this->addressService->delete($request->user(), $address);

        return $this->success(null, 'Address deleted successfully.');
    }
}
