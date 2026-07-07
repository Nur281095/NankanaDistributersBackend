<?php

use App\Http\Controllers\Api\V1\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.active'])->group(function (): void {
    Route::post('devices/token', [DeviceTokenController::class, 'store']);
    Route::delete('devices/token', [DeviceTokenController::class, 'destroy']);
});
