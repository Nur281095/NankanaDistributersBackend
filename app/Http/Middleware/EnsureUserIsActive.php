<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->status !== UserStatus::Active) {
            return ApiResponse::error(
                'Your account is not active. Please contact support.',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
