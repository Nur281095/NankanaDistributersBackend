<?php

use App\Http\Controllers\JazzCashCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payments/jazzcash/checkout/{token}', [JazzCashCheckoutController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('payments.jazzcash.checkout');
