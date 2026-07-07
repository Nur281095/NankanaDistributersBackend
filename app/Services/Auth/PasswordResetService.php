<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\EmailService;
use App\Support\EmailPlaceholderBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetService
{
    private const TOKEN_EXPIRY_MINUTES = 60;

    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    /**
     * Always returns silently — never reveals whether the phone exists.
     */
    public function sendResetLink(string $phone): void
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            return;
        }

        $plainToken = Str::random(64);
        $identifier = $this->resetIdentifier($user);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $identifier],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ],
        );

        if ($user->email !== null && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $this->emailService->queue(
                templateSlug: 'password_reset',
                recipient: $user->email,
                placeholders: EmailPlaceholderBuilder::forPasswordReset($user, $plainToken),
                referenceType: 'user',
                referenceId: $user->id,
            );

            return;
        }

        if (app()->environment('local')) {
            logger()->info('Password reset token generated (no email on file).', [
                'phone' => $phone,
                'token' => $plainToken,
            ]);
        }
    }

    public function resetPassword(string $phone, string $token, string $password): void
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw new BusinessException(
                'Invalid or expired reset token.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $identifier = $this->resetIdentifier($user);
        $record = DB::table('password_reset_tokens')->where('email', $identifier)->first();

        if ($record === null || ! Hash::check($token, $record->token)) {
            throw new BusinessException(
                'Invalid or expired reset token.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($record->created_at === null) {
            throw new BusinessException(
                'Invalid or expired reset token.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $createdAt = \Illuminate\Support\Carbon::parse($record->created_at);

        if ($createdAt->addMinutes(self::TOKEN_EXPIRY_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $identifier)->delete();

            throw new BusinessException(
                'Reset token has expired. Please request a new one.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->forceFill(['password' => $password])->save();

        DB::table('password_reset_tokens')->where('email', $identifier)->delete();

        $user->tokens()->delete();
    }

    private function resetIdentifier(User $user): string
    {
        return $user->email ?? $user->phone;
    }
}
