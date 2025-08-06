<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\CurrencyRateController;
use App\Http\Controllers\Api\DailyCurrencyRateController;


Route::middleware('auth.token')->group(function () {
    // Kursy walut
    Route::post('/currency-rates', [CurrencyRateController::class, 'getRates']);
    Route::post('/currency-rates/daily-update', [DailyCurrencyRateController::class, 'updateTodayRates']);
    
});
