<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\MedicationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('calendar/{year?}', [CalendarController::class, 'index'])
        ->whereNumber('year')
        ->name('calendar');
    Route::post('migraine-scores', [CalendarController::class, 'store'])
        ->name('migraine-scores.store');
    Route::patch('migraine-scores/{migraineScore}', [CalendarController::class, 'update'])
        ->name('migraine-scores.update');

    Route::get('medications', [MedicationController::class, 'index'])
        ->name('medications');
    Route::post('medications', [MedicationController::class, 'store'])
        ->name('medications.store');
    Route::patch('medications/{medication}', [MedicationController::class, 'update'])
        ->name('medications.update');
});

require __DIR__.'/settings.php';
