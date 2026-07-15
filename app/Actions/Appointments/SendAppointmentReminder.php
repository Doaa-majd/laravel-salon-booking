<?php

namespace App\Actions\Appointments;

use App\Enums\ReminderChannel;
use App\Models\Appointment;
use App\Models\ReminderLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

/**
 * Build a WhatsApp reminder deep link for an appointment's client and record
 * that a reminder was issued (reminder_logs), per the analysis doc §7.
 *
 * No message is sent server-side — the staff member opens the returned wa.me
 * link to message the client from their own WhatsApp — but each build is logged
 * so reminder activity is auditable. The link targets the client's number and is
 * pre-filled from the salon's reminder_template with {client}/{salon}/{date}/
 * {time} substituted (falling back to a default template when none is set).
 */
class SendAppointmentReminder
{
    /**
     * Log a WhatsApp reminder for the appointment and return the wa.me link.
     */
    public function handle(Appointment $appointment, ?int $sentBy = null): string
    {
        $appointment->loadMissing('client');
        $setting = Setting::current();

        $url = $this->whatsappUrl($appointment, $setting);

        ReminderLog::query()->create([
            'appointment_id' => $appointment->id,
            'channel' => ReminderChannel::Whatsapp,
            'sent_at' => now(),
            'sent_by' => $sentBy ?? Auth::id(),
        ]);

        return $url;
    }

    /**
     * The client-facing wa.me deep link, pre-filled with the reminder message.
     */
    protected function whatsappUrl(Appointment $appointment, Setting $setting): string
    {
        $number = (string) preg_replace('/\D+/', '', (string) $appointment->client?->phone);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->message($appointment, $setting));
    }

    /**
     * The reminder text with template variables substituted.
     */
    protected function message(Appointment $appointment, Setting $setting): string
    {
        $template = $setting->reminder_template
            ?: 'مرحباً {client}، نذكّرك بموعدك في {salon} يوم {date} الساعة {time}.';

        return strtr($template, [
            '{client}' => (string) $appointment->client?->name,
            '{salon}' => $setting->salon_name,
            '{date}' => $appointment->datetime_start->format('Y-m-d'),
            '{time}' => $appointment->datetime_start->format('H:i'),
        ]);
    }
}
