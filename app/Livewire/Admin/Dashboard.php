<?php

namespace App\Livewire\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The admin operational dashboard (analysis doc §5): headline KPI cards plus a
 * chronological list of today's appointments. Read-only aggregates over the
 * appointment records — everything is scoped to the salon-local "today" and
 * excludes cancelled bookings from counts and expected revenue.
 */
#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * Today's appointments (salon-local), excluding cancelled, ordered by start.
     *
     * @return Collection<int, Appointment>
     */
    protected function todaysAppointments(Setting $setting): Collection
    {
        $today = Carbon::now($setting->timezone)->toDateString();

        return Appointment::query()
            ->with(['client', 'staff', 'services'])
            ->whereDate('datetime_start', $today)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->orderBy('datetime_start')
            ->get();
    }

    public function render(): mixed
    {
        $setting = Setting::current();
        $now = Carbon::now($setting->timezone);

        $appointments = $this->todaysAppointments($setting);

        // Expected revenue = Σ price_snapshot of today's non-cancelled bookings.
        $revenue = $appointments->sum(fn (Appointment $appointment): float => (float) $appointment->price_snapshot);
        $noShows = $appointments->where('status', AppointmentStatus::NoShow)->count();

        // The next booking still ahead of us today (ignoring already-missed ones).
        $nextUp = $appointments->first(
            fn (Appointment $appointment): bool => $appointment->status !== AppointmentStatus::NoShow
                && Carbon::parse($appointment->datetime_start->format('Y-m-d H:i:s'), $setting->timezone)->gt($now),
        );

        return view('livewire.admin.dashboard', [
            'appointments' => $appointments,
            'todayCount' => $appointments->count(),
            'revenue' => $revenue,
            'noShows' => $noShows,
            'nextUp' => $nextUp,
            'currency' => $setting->currency->value,
        ]);
    }
}
