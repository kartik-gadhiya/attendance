<?php

use App\Http\Controllers\UserTimeClockController;
use App\Http\Controllers\NewUserTimeClockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// User Time Clock API Routes
Route::prefix('time-clock')->group(function () {
    Route::post('/', [UserTimeClockController::class, 'store']);
    Route::get('/', [UserTimeClockController::class, 'index']);
    // Route::get('/{userTimeClock}', [UserTimeClockController::class, 'show']);
});

Route::post('/time-clock-new', [NewUserTimeClockController::class, 'store']);
Route::post('/time-clock-new-one', [NewUserTimeClockController::class, 'store']);
