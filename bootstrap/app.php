<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('inventory:sweep-low-stock')->dailyAt('09:00');
        $schedule->command('offers:expire')->dailyAt('00:15');
        $schedule->command('queue:prune-failed', ['--hours' => 168])->weekly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->api(append: [
            ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'The given data was invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Unauthenticated.',
                Response::HTTP_UNAUTHORIZED,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'This action is unauthorized.',
                Response::HTTP_FORBIDDEN,
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Resource not found.',
                Response::HTTP_NOT_FOUND,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Route not found.',
                Response::HTTP_NOT_FOUND,
            );
        });

        $exceptions->render(function (BusinessException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                $exception->statusCode(),
                $exception->errors(),
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof NotFoundHttpException) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Request failed.',
                $exception->getStatusCode(),
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            return ApiResponse::error(
                'Something went wrong. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
