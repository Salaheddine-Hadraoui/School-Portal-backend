<?php

use App\Http\Controllers\Auth\AuthSocialiteController;
use Illuminate\Support\Facades\Route;



Route::prefix('auth')->group(function () {
    Route::get('{provider}/redirect', [AuthSocialiteController::class, 'redirectToSOcilaProvider']);

    Route::get('{provider}/callback', [AuthSocialiteController::class, 'callbackToSocialProvider']);
});
Route::get('/', function () {
    return response(['is running', true], 200);
});
require __DIR__ . '/auth.php';
