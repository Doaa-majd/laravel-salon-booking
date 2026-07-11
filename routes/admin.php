<?php

use App\Livewire\Admin\Settings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/settings')->name('dashboard');

        Route::livewire('settings', Settings::class)->name('settings.edit');
    });
