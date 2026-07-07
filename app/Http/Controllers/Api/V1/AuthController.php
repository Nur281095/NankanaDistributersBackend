<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success(
            AuthTokenResource::make($result)->resolve(),
            'Registration successful.',
            Response::HTTP_CREATED,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('phone'),
            $request->validated('password'),
        );

        return $this->success(
            AuthTokenResource::make($result)->resolve(),
            'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $accessToken = $user->currentAccessToken();

            if ($accessToken !== null) {
                $accessToken->delete();
            } elseif ($plainTextToken = $request->bearerToken()) {
                if (str_contains($plainTextToken, '|')) {
                    [$tokenId] = explode('|', $plainTextToken, 2);
                    $user->tokens()->whereKey($tokenId)->delete();
                }
            }
        }

        return $this->success(null, 'Logged out successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->validated('phone'));

        return $this->success(
            null,
            'If an account exists for this phone number, a reset link has been sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->authService->resetPassword(
            $validated['phone'],
            $validated['token'],
            $validated['password'],
        );

        return $this->success(null, 'Password has been reset successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->success(
            UserResource::make($request->user())->resolve(),
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            UserResource::make($user)->resolve(),
            'Profile updated successfully.',
        );
    }
}
