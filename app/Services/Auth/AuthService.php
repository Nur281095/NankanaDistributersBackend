<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    /**
     * @param  array{name: string, phone: string, email?: string|null, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'status' => UserStatus::Active,
        ]);

        return $this->issueToken($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $phone, string $password): array
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw new BusinessException(
                'Invalid phone number or password.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->ensureUserCanAuthenticate($user);

        return $this->issueToken($user);
    }

    public function sendPasswordResetLink(string $phone): void
    {
        $this->passwordResetService->sendResetLink($phone);
    }

    public function resetPassword(string $phone, string $token, string $password): void
    {
        $this->passwordResetService->resetPassword($phone, $token, $password);
    }

    /**
     * @param  array{name?: string, email?: string|null, phone?: string, password?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['phone']) && $data['phone'] !== $user->phone) {
            $exists = User::query()
                ->where('phone', $data['phone'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                throw new BusinessException(
                    'This phone number is already registered.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['phone' => ['This phone number is already registered.']],
                );
            }
        }

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            if ($data['email'] !== null) {
                $exists = User::query()
                    ->where('email', $data['email'])
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($exists) {
                    throw new BusinessException(
                        'This email is already registered.',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        ['email' => ['This email is already registered.']],
                    );
                }
            }
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user->fresh();
    }

    private function ensureUserCanAuthenticate(User $user): void
    {
        if ($user->status === UserStatus::Blocked) {
            throw new BusinessException(
                'Your account has been blocked. Please contact support.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->status === UserStatus::Inactive) {
            throw new BusinessException(
                'Your account is inactive. Please contact support.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->password === null) {
            throw new BusinessException(
                'Password login is not available for this account.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        $user->tokens()->delete();

        $token = $user->createToken('mobile-api')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
