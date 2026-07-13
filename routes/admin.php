<?php

use App\Livewire\Admin\Appointments;
use App\Livewire\Admin\ClientProfile;
use App\Livewire\Admin\Clients;
use App\Livewire\Admin\ServiceCategories;
use App\Livewire\Admin\Services;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Staff;
use App\Livewire\Admin\StaffSchedule;
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
        Route::livewire('staff/{staff}/schedule', StaffSchedule::class)->name('staff.schedule');

        Route::livewire('clients', Clients::class)->name('clients.index');
        Route::livewire('clients/{client}', ClientProfile::class)->name('clients.show');

        Route::livewire('appointments', Appointments::class)->name('appointments.index');
    });
