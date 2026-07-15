<?php

use App\Actions\Appointments\SendAppointmentReminder;
use App\Enums\ReminderChannel;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

test('sending a reminder logs it and returns a wa.me link with substituted variables', function () {
    Setting::factory()->create([
        'salon_name' => 'Glow Salon',
        'reminder_template' => 'Hi {client}, reminder for {salon} on {date} at {time}.',
    ]);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $client = Client::factory()->create(['name' => 'Noura', 'phone' => '+966512345678']);
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'datetime_start' => Carbon::parse('2026-07-20 15:30'),
    ]);

    $url = app(SendAppointmentReminder::class)->handle($appointment);

    // Log written.
    $log = ReminderLog::query()->firstOrFail();
    expect($log->appointment_id)->toBe($appointment->id);
    expect($log->channel)->toBe(ReminderChannel::Whatsapp);
    expect($log->sent_by)->toBe($user->id);

    // wa.me link targets the client's digits and carries the filled message.
    expect($url)->toStartWith('https://wa.me/966512345678?text=');

    $text = rawurldecode((string) parse_url($url, PHP_URL_QUERY));
    expect($text)->toContain('Hi Noura');
    expect($text)->toContain('Glow Salon');
    expect($text)->toContain('2026-07-20');
    expect($text)->toContain('15:30');
});

test('sending a reminder falls back to a default template when none is configured', function () {
    Setting::factory()->create(['reminder_template' => null]);

    $this->actingAs(User::factory()->admin()->create());

    $client = Client::factory()->create(['name' => 'Sara', 'phone' => '+966500000000']);
    $appointment = Appointment::factory()->create(['client_id' => $client->id]);

    $url = app(SendAppointmentReminder::class)->handle($appointment);

    expect($url)->toStartWith('https://wa.me/966500000000?text=');
    expect(rawurldecode((string) parse_url($url, PHP_URL_QUERY)))->toContain('Sara');
    expect(ReminderLog::query()->count())->toBe(1);
});
