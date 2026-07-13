<?php

namespace App\Livewire\Public;

use App\Models\Appointment;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Public confirmation page shown after a booking is created. Read-only; the
 * appointment id in the URL is the capability (no auth), matching the guest
 * booking flow. Offers a WhatsApp link to the salon and an .ics download.
 */
#[Layout('layouts.public')]
#[Title('Booking confirmed')]
class BookingConfirmation extends Component
{
    public Appointment $appointment;

    public function mount(Appointment $appointment): void
    {
        $this->appointment = $appointment->load(['services', 'staff', 'client']);
    }

    /**
     * A wa.me deep link to the salon's WhatsApp number, pre-filled with the
     * booking reference, or null when no WhatsApp number is configured.
     */
    protected function whatsappUrl(Setting $setting): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $setting->whatsapp_number);

        if ($number === null || $number === '') {
            return null;
        }

        $text = __('Hello, I have a booking (#:id) on :date at :time.', [
            'id' => $this->appointment->id,
            'date' => $this->appointment->datetime_start->format('Y-m-d'),
            'time' => $this->appointment->datetime_start->format('H:i'),
        ]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    public function render(): mixed
    {
        $setting = Setting::current();

        return view('livewire.public.booking-confirmation', [
            'setting' => $setting,
            'whatsappUrl' => $this->whatsappUrl($setting),
            'icsUrl' => route('book.ics', $this->appointment),
        ]);
    }
}
