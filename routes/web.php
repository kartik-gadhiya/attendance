<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TimeClockWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Time Clock Web Routes
Route::prefix('time-clock')->group(function () {
    // Display the time clock page
    Route::get('/', [TimeClockWebController::class, 'index'])->name('time-clock.index');

    // AJAX endpoints
    Route::get('/users', [TimeClockWebController::class, 'getUsers'])->name('time-clock.users');
    Route::get('/records', [TimeClockWebController::class, 'getRecords'])->name('time-clock.records');
    Route::post('/records', [TimeClockWebController::class, 'store'])->name('time-clock.store');
    Route::post('/records/{id}', [TimeClockWebController::class, 'update'])->name('time-clock.update');
});
