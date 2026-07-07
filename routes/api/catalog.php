<?php

use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('companies', [CompanyController::class, 'index']);
Route::get('companies/{company}', [CompanyController::class, 'show']);
Route::get('companies/{company}/brands', [CompanyController::class, 'brands']);

Route::get('brands/{brand}', [BrandController::class, 'show']);
Route::get('brands/{brand}/products', [BrandController::class, 'products']);

Route::get('products/search', [ProductController::class, 'search']);
Route::get('products/suggested', [ProductController::class, 'suggested']);
Route::get('products/{product}', [ProductController::class, 'show']);
