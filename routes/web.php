<?php

declare(strict_types=1);

use App\Http\Controllers\PriceFeedController;
use App\Http\Controllers\PricePageController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', PricePageController::class)->name('prices');
Route::get('/api/prices', PriceFeedController::class)
    ->middleware('throttle:60,1')
    ->name('api.prices');
Route::post('/submit', SubmissionController::class)
    ->middleware('throttle:5,1')
    ->name('submit');
