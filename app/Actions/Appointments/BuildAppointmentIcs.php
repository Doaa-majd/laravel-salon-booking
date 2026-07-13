<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds an iCalendar (.ics) document for a booked appointment so the client
 * can add it to their calendar from the confirmation page.
 *
 * Appointment datetimes are stored as naive salon-local wall clock (ADR-0012),
 * so they are re-parsed in the salon timezone and emitted as absolute UTC
 * instants (the trailing "Z" form), which is unambiguous for any calendar app
 * regardless of the viewer's timezone.
 */
class BuildAppointmentIcs
{
    /**
     * Render the appointment as an iCalendar document (CRLF line endings).
     */
    public function handle(Appointment $appointment): string
    {
        $appointment->loadMissing(['services', 'staff']);

        $setting = Setting::current();
        $timezone = $setting->timezone;

        $start = $this->toUtc($appointment->datetime_start, $timezone);
        $end = $this->toUtc($appointment->datetime_end, $timezone);
        $stamp = Carbon::now('UTC');

        $summary = $appointment->services->pluck('name')->join(', ');
        $summary = $summary === '' ? $setting->salon_name : $summary.' — '.$setting->salon_name;

        $description = __('Appointment with :staff at :salon.', [
            'staff' => $appointment->staff->name,
            'salon' => $setting->salon_name,
        ]);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape($setting->salon_name).'//Booking//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:appointment-'.$appointment->id.'@'.$this->host(),
            'DTSTAMP:'.$stamp->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary),
            'DESCRIPTION:'.$this->escape($description),
        ];

        if (filled($setting->address)) {
            $lines[] = 'LOCATION:'.$this->escape($setting->address);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Re-interpret a stored salon-local wall-clock datetime as an absolute UTC instant.
     */
    private function toUtc(CarbonInterface $datetime, string $timezone): Carbon
    {
        return Carbon::parse($datetime->format('Y-m-d H:i:s'), $timezone)->utc();
    }

    /**
     * Escape a text value per RFC 5545 (backslash, comma, semicolon, newlines).
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ',', ';', "\r\n", "\n"],
            ['\\\\', '\\,', '\\;', '\\n', '\\n'],
            $value,
        );
    }

    private function host(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'salon.local');
    }
}
