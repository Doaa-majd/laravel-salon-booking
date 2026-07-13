<?php

namespace App\Livewire\Public;

use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Setting;
use App\Rules\PhoneNumber;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Guest self-service: look up upcoming appointments by phone number and cancel
 * one within the allowed lead-time window. No auth — the phone number is the
 * lookup key, reusing the shared PhoneNumber normalization (ADR-0013) so it
 * matches the same client record the booking created.
 */
#[Layout('layouts.public')]
#[Title('My booking')]
class MyBooking extends Component
{
    /**
     * Cancellations are only allowed while the appointment is at least this
     * many minutes away — a client cannot cancel a booking about to start.
     */
    public const CANCELLATION_LEAD_MINUTES = 120;

    public string $phone = '';

    public bool $searched = false;

    /**
     * Look up the caller's upcoming appointments by normalized phone number.
     */
    public function lookup(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);
        $this->validate(['phone' => ['required', new PhoneNumber]]);

        $this->searched = true;
    }

    /**
     * Cancel one of the caller's own upcoming appointments, if still within the
     * allowed lead-time window.
     */
    public function cancel(int $appointmentId, ChangeAppointmentStatus $action): void
    {
        $appointment = $this->upcomingAppointments()->firstWhere('id', $appointmentId);

        if ($appointment === null) {
            Flux::toast(variant: 'danger', text: __('Appointment not found.'));

            return;
        }

        $timezone = Setting::current()->timezone;
        $start = Carbon::parse($appointment->datetime_start->format('Y-m-d H:i:s'), $timezone);

        if ($start->lte(Carbon::now($timezone)->addMinutes(self::CANCELLATION_LEAD_MINUTES))) {
            Flux::toast(variant: 'danger', text: __('This booking is too close to its start time to cancel online. Please contact the salon.'));

            return;
        }

        try {
            $action->handle($appointment, AppointmentStatus::Cancelled);
        } catch (InvalidStatusTransitionException) {
            Flux::toast(variant: 'danger', text: __('This booking can no longer be cancelled.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Your booking has been cancelled.'));
    }

    /**
     * The caller's upcoming, still-active appointments (pending/confirmed),
     * empty until a valid phone has been looked up.
     *
     * @return Collection<int, Appointment>
     */
    protected function upcomingAppointments(): Collection
    {
        if (! $this->searched || $this->phone === '') {
            return new Collection;
        }

        $client = Client::query()->where('phone', $this->phone)->first();

        if ($client === null) {
            return new Collection;
        }

        $now = Carbon::now(Setting::current()->timezone)->format('Y-m-d H:i:s');

        return $client->appointments()
            ->with(['staff', 'services'])
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->where('datetime_start', '>=', $now)
            ->orderBy('datetime_start')
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.public.my-booking', [
            'appointments' => $this->upcomingAppointments(),
            'leadHours' => intdiv(self::CANCELLATION_LEAD_MINUTES, 60),
        ]);
    }
}
