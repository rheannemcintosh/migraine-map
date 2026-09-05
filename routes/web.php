<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('calendar/{year?}', [CalendarController::class, 'index'])
        ->whereNumber('year')
        ->name('calendar');
    Route::post('migraine-scores', [CalendarController::class, 'store'])
        ->name('migraine-scores.store');
});

require __DIR__.'/settings.php';
