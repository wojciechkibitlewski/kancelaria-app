<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\CurrencyRateController;
use App\Http\Controllers\Api\DailyCurrencyRateController;
use App\Http\Controllers\Api\InstallmentController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Api\UserController;


// autoryzcja
Route::post('/auth/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/password/reset',       [PasswordController::class, 'reset']);

Route::middleware('auth')->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::post('/auth/password/create-link', [PasswordController::class, 'createLink']);

    // lista / podgląd

    Route::get('/users',            [UserController::class, 'index']);
    Route::get('/users/{user}',     [UserController::class, 'show']);

    // tworzenie (kierownik/zarząd)
    Route::post('/users',           [UserController::class, 'store']);

    // edycja siebie
    Route::put('/users/me',         [UserController::class, 'updateSelf']);
    Route::get('/auth/me', [LoginController::class, 'me']);
    
    // edycja przez kierownika/zarząd
    Route::put('/users/{user}',     [UserController::class, 'update']);

    // (de)aktywacja
    Route::post('/users/{user}/active', [UserController::class, 'setActive']);

    // ponowna wysyłka zaproszenia
    Route::post('/users/{user}/resend-invite', [UserController::class, 'resendInvite']);


});

Route::middleware('auth.token')->group(function () {
   

    // Kursy walut
    Route::post('/currency-rates', [CurrencyRateController::class, 'getRates']);
    Route::post('/currency-rates/daily-update', [DailyCurrencyRateController::class, 'updateTodayRates']);
    Route::post('/installments/calculate', [InstallmentController::class, 'index']);

});
