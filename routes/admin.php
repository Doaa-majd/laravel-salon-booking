<?php

use App\Livewire\Admin\ServiceCategories;
use App\Livewire\Admin\Services;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Staff;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/settings')->name('dashboard');

        Route::livewire('settings', Settings::class)->name('settings.edit');

        Route::livewire('service-categories', ServiceCategories::class)->name('service-categories.index');
        Route::livewire('services', Services::class)->name('services.index');
        Route::livewire('staff', Staff::class)->name('staff.index');
    });
