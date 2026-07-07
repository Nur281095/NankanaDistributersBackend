<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class Controller extends BaseController
{
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }

    /**
     * @param  class-string<\Illuminate\Http\Resources\Json\JsonResource>  $resourceClass
     */
    protected function paginated(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        ?string $message = null,
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $resourceClass, $message);
    }
}
