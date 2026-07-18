<?php

use App\Http\Controllers\Api\V1\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('home', HomeController::class);
