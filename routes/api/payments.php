<?php

use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('payments/callback/{gateway}', [PaymentController::class, 'callback']);

Route::middleware(['auth:sanctum', 'user.active'])->group(function (): void {
    Route::post('payments/initiate', [PaymentController::class, 'initiate']);
});
