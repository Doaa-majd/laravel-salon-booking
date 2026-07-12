<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The availability engine — the heart of the booking system.
 *
 * Given a set of services (whose durations sum to a total D), an optional
 * specific staff member (or "any" when null), and a date, it returns the
 * bookable start times together with a suggested staff member for each.
 *
 * The algorithm follows section 4 of docs/salon-booking-build-analysis.md:
 * weekday → salon working hours → intersect each candidate staff's weekly
 * schedule → subtract their time-off → subtract their non-cancelled
 * appointments → step-generate candidate start times → keep only those that
 * fit the window, do not overlap a busy interval, and lie beyond now + buffer.
 *
 * All wall-clock reasoning happens in the salon's timezone (settings.timezone),
 * per ADR-0011/ADR-0012. Appointment datetimes are stored as naive salon-local
 * wall clock (Laravel formats without shifting timezone), so they are matched by
 * date and re-labelled into the salon timezone before comparison. This service
 * performs no writes and holds no Livewire state, so it is fully unit-testable.
 */
class AvailabilityService
{
    /**
     * @param  int  $stepMinutes  Granularity of generated start times.
     * @param  int  $bufferMinutes  Minimum lead time between now and a slot.
     */
    public function __construct(
        private readonly int $stepMinutes = 15,
        private readonly int $bufferMinutes = 60,
    ) {}

    /**
     * Whether two half-open intervals [aStart, aEnd) and [bStart, bEnd)
     * overlap. Touching boundaries (aEnd == bStart) do NOT overlap, so a slot
     * that ends exactly when another begins is valid.
     */
    public static function intervalsOverlap(CarbonInterface $aStart, CarbonInterface $aEnd, CarbonInterface $bStart, CarbonInterface $bEnd): bool
    {
        return $aStart->lt($bEnd) && $bStart->lt($aEnd);
    }

    /**
     * Compute available start times for the given services/staff/date.
     *
     * @param  array<int, int>  $serviceIds  Services to book together.
     * @param  int|null  $staffId  A specific staff member, or null for "any".
     * @param  string|Carbon  $date  The target date (salon-local).
     * @return array<int, array{time: string, datetime: string, staff_id: int, staff_name: string}>
     *                                                                                              Slots sorted ascending by start time; one entry per
     *                                                                                              time (for "any", the earliest-id free staff is suggested).
     */
    public function availableSlots(array $serviceIds, ?int $staffId, string|Carbon $date): array
    {
        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));

        if ($serviceIds === []) {
            return [];
        }

        $setting = Setting::current();
        $timezone = $setting->timezone;

        $day = $this->resolveDay($date, $timezone);
        $weekday = $day->dayOfWeek; // 0 = Sunday .. 6 = Saturday

        // Salon closed that weekday → no slots.
        $hours = $setting->working_hours[$weekday] ?? null;
        if ($hours === null || $hours['closed']) {
            return [];
        }
        $salonOpen = $this->timeOn($day, $hours['open']);
        $salonClose = $this->timeOn($day, $hours['close']);

        // Total duration D — every requested service must be active and exist.
        $services = Service::query()->active()->whereKey($serviceIds)->get();
        if ($services->count() !== count($serviceIds)) {
            return [];
        }
        $duration = (int) $services->sum('duration_minutes');
        if ($duration <= 0) {
            return [];
        }

        $candidates = $this->candidateStaff($serviceIds, $staffId);
        if ($candidates->isEmpty()) {
            return [];
        }

        $earliest = Carbon::now($timezone)->addMinutes($this->bufferMinutes);

        /** @var array<string, array{time: string, datetime: string, staff_id: int, staff_name: string}> $slots */
        $slots = [];

        foreach ($candidates as $staff) {
            $window = $this->workingWindow($staff, $weekday, $day, $salonOpen, $salonClose);
            if ($window === null) {
                continue;
            }
            [$windowStart, $windowEnd] = $window;

            $busy = $this->busyIntervals($staff, $day);
            if ($busy === null) {
                // Full-day time-off — this staff is unavailable all day.
                continue;
            }

            $this->collectSlots($slots, $staff, $windowStart, $windowEnd, $duration, $busy, $earliest);
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Resolve the target date to salon-local midnight.
     */
    private function resolveDay(string|Carbon $date, string $timezone): Carbon
    {
        $day = $date instanceof Carbon
            ? $date->copy()->setTimezone($timezone)
            : Carbon::parse($date, $timezone);

        return $day->startOfDay();
    }

    /**
     * Build a salon-local Carbon at the given "H:i" (or "H:i:s") time on $day.
     */
    private function timeOn(Carbon $day, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->copy()->setTime($hour, $minute);
    }

    /**
     * Active staff who provide every requested service; optionally narrowed to
     * one specific staff member. Ordered by id so "any" resolution is stable.
     *
     * @param  array<int, int>  $serviceIds
     * @return Collection<int, Staff>
     */
    private function candidateStaff(array $serviceIds, ?int $staffId): Collection
    {
        $query = Staff::query()
            ->active()
            ->providesAllServices($serviceIds)
            ->orderBy('id');

        if ($staffId !== null) {
            $query->whereKey($staffId);
        }

        return $query->get();
    }

    /**
     * Intersect the salon hours with the staff member's weekly schedule for
     * this weekday. Returns [start, end] or null when the staff is off, has no
     * schedule row, or the intersection is empty.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function workingWindow(Staff $staff, int $weekday, Carbon $day, Carbon $salonOpen, Carbon $salonClose): ?array
    {
        /** @var StaffSchedule|null $schedule */
        $schedule = $staff->schedules->firstWhere('weekday', $weekday);

        if ($schedule === null || $schedule->is_off) {
            return null;
        }

        $windowStart = $this->max($salonOpen, $this->timeOn($day, $schedule->start_time));
        $windowEnd = $this->min($salonClose, $this->timeOn($day, $schedule->end_time));

        if ($windowStart->gte($windowEnd)) {
            return null;
        }

        return [$windowStart, $windowEnd];
    }

    /**
     * Busy intervals for the staff member on $day: partial time-off windows
     * plus non-cancelled appointments. Returns null when a full-day time-off
     * makes the staff unavailable for the entire date.
     *
     * @return array<int, array{0: CarbonInterface, 1: CarbonInterface}>|null
     */
    private function busyIntervals(Staff $staff, Carbon $day): ?array
    {
        $busy = [];

        /** @var Collection<int, StaffTimeOff> $timeOff */
        $timeOff = $staff->timeOff
            ->filter(fn (StaffTimeOff $off): bool => $off->date->isSameDay($day));

        foreach ($timeOff as $off) {
            if ($off->start_time === null || $off->end_time === null) {
                return null; // Full-day leave.
            }

            $busy[] = [$this->timeOn($day, $off->start_time), $this->timeOn($day, $off->end_time)];
        }

        // Appointment datetimes are stored as naive salon-local wall clock
        // (Laravel formats without shifting timezone), so they are matched by
        // date and their raw wall-clock is re-labelled into the salon timezone.
        $appointments = Appointment::query()
            ->where('staff_id', $staff->id)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->whereDate('datetime_start', $day->toDateString())
            ->get(['datetime_start', 'datetime_end']);

        foreach ($appointments as $appointment) {
            $busy[] = [
                Carbon::parse($appointment->datetime_start->format('Y-m-d H:i:s'), $day->timezone),
                Carbon::parse($appointment->datetime_end->format('Y-m-d H:i:s'), $day->timezone),
            ];
        }

        return $busy;
    }

    /**
     * Step through the working window generating candidate start times, adding
     * the valid ones to $slots (keyed by "H:i", first free staff wins).
     *
     * @param  array<string, array{time: string, datetime: string, staff_id: int, staff_name: string}>  $slots
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $busy
     */
    private function collectSlots(array &$slots, Staff $staff, Carbon $windowStart, Carbon $windowEnd, int $duration, array $busy, Carbon $earliest): void
    {
        $cursor = $windowStart->copy();

        while (true) {
            $candidateEnd = $cursor->copy()->addMinutes($duration);

            if ($candidateEnd->gt($windowEnd)) {
                break;
            }

            if ($cursor->gte($earliest) && ! $this->overlapsAny($cursor, $candidateEnd, $busy)) {
                $key = $cursor->format('H:i');

                if (! isset($slots[$key])) {
                    $slots[$key] = [
                        'time' => $key,
                        'datetime' => $cursor->toIso8601String(),
                        'staff_id' => $staff->id,
                        'staff_name' => $staff->name,
                    ];
                }
            }

            $cursor->addMinutes($this->stepMinutes);
        }
    }

    /**
     * Whether [start, end) overlaps any of the given busy intervals.
     *
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $busy
     */
    private function overlapsAny(Carbon $start, Carbon $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if (self::intervalsOverlap($start, $end, $busyStart, $busyEnd)) {
                return true;
            }
        }

        return false;
    }

    private function max(Carbon $a, Carbon $b): Carbon
    {
        return $a->gte($b) ? $a->copy() : $b->copy();
    }

    private function min(Carbon $a, Carbon $b): Carbon
    {
        return $a->lte($b) ? $a->copy() : $b->copy();
    }
}
