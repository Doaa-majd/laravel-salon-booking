<?php

use App\Http\Controllers\Api\AvailableSlotsController;
use App\Http\Controllers\BookingIcsController;
use App\Livewire\Public\BookingConfirmation;
use App\Livewire\Public\BookingWizard;
use App\Livewire\Public\MyBooking;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Public, guest-facing booking flow (mobile-first, RTL).
Route::livewire('book', BookingWizard::class)->name('book');
Route::livewire('book/confirmed/{appointment}', BookingConfirmation::class)->name('book.confirmed');
Route::get('book/{appointment}/calendar.ics', BookingIcsController::class)->name('book.ics');
Route::livewire('my-booking', MyBooking::class)->name('my-booking');

// Internal read-only availability endpoint for the public booking wizard.
Route::get('api/available-slots', AvailableSlotsController::class)->name('api.available-slots');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
