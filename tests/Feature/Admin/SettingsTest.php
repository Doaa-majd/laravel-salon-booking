<?php

use App\Livewire\Admin\Settings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('admin can view the salon settings page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/settings')->assertOk();
});

test('staff cannot view the salon settings page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/settings')->assertForbidden();
});

test('guests are redirected from admin settings to login', function () {
    $this->get('/admin/settings')->assertRedirect(route('login'));
});

test('the admin index redirects to the settings page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin')->assertRedirect('/admin/settings');
});

test('admin can update salon settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Settings::class)
        ->set('salon_name', 'My Salon')
        ->set('primary_color', '#123456')
        ->set('currency', 'AED')
        ->set('timezone', 'Asia/Dubai')
        ->set('week_start', 6)
        ->set('phone', '+966500000000')
        ->set('whatsapp_number', '+966500000000')
        ->set('address', 'Riyadh')
        ->set('reminder_template', 'Hi {client}')
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::current();

    expect($setting->salon_name)->toBe('My Salon');
    expect($setting->primary_color)->toBe('#123456');
    expect($setting->currency->value)->toBe('AED');
    expect($setting->timezone)->toBe('Asia/Dubai');
    expect($setting->week_start)->toBe(6);
    expect($setting->reminder_template)->toBe('Hi {client}');
});

test('working hours round-trip through the settings JSON column', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Settings::class)
        ->set('working_hours.0.open', '09:00')
        ->set('working_hours.0.close', '17:00')
        ->set('working_hours.0.closed', false)
        ->set('working_hours.5.closed', true)
        ->call('save')
        ->assertHasNoErrors();

    $hours = Setting::current()->working_hours;

    expect($hours[0]['open'])->toBe('09:00');
    expect($hours[0]['close'])->toBe('17:00');
    expect($hours[0]['closed'])->toBeFalse();
    expect($hours[5]['closed'])->toBeTrue();
    expect($hours)->toHaveCount(7);
});

test('settings validation rejects an invalid color and empty name', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Settings::class)
        ->set('salon_name', '')
        ->set('primary_color', 'red')
        ->call('save')
        ->assertHasErrors(['salon_name', 'primary_color']);
});

test('closing time must be after opening time', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Settings::class)
        ->set('working_hours.0.open', '18:00')
        ->set('working_hours.0.close', '10:00')
        ->set('working_hours.0.closed', false)
        ->call('save')
        ->assertHasErrors('working_hours.0.close');
});

test('admin can upload a salon logo', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Settings::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors();

    $logo = Setting::current()->logo;

    expect($logo)->not->toBeNull();
    Storage::disk('public')->assertExists($logo);
});

test('the settings accessor returns the single row and seeds defaults', function () {
    $first = Setting::current();
    $second = Setting::current();

    expect($first->id)->toBe($second->id);
    expect(Setting::query()->count())->toBe(1);
    expect($first->working_hours)->toHaveCount(7);
});
