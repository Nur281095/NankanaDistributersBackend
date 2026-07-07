<?php

use App\Http\Controllers\Api\V1\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::post('checkout/summary', [CheckoutController::class, 'summary']);
Route::post('checkout/place-order', [CheckoutController::class, 'placeOrder']);
