<?php

use App\Http\Controllers\Api\AvailableSlotsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Internal read-only availability endpoint for the public booking wizard.
Route::get('api/available-slots', AvailableSlotsController::class)->name('api.available-slots');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
