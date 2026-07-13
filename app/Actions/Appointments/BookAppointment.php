<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Rules\PhoneNumber;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The authoritative booking writer — the only path that creates appointments.
 *
 * It opens a transaction, locks the target staff row to serialize concurrent
 * bookings (ADR-0007), re-runs the availability engine as the authoritative
 * re-check (never trusting a frontend-selected slot, per Booking Rules), then
 * writes the appointment together with immutable price/duration snapshots for
 * each service (ADR-0006). Appointment datetimes are written as salon-local
 * wall clock to stay consistent with the availability engine (ADR-0012).
 *
 * Because the staff-row lock is the first read in the transaction, the
 * consistent reads inside the re-check see the latest committed appointments,
 * so a slot filled by a concurrent booking is rejected with
 * SlotUnavailableException.
 */
class BookAppointment
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Create an appointment for the given services/staff/start.
     *
     * Provide either a `client_id` (existing client) or an inline `client`
     * (name + phone) which is found-or-created by normalized phone. A null
     * `staff_id` resolves "any available" staff for the requested time.
     *
     * @param  array{
     *     service_ids: array<int, int>,
     *     start: string|Carbon,
     *     staff_id?: int|null,
     *     client_id?: int|null,
     *     client?: array{name: string, phone: string, email?: string|null},
     *     source?: AppointmentSource,
     *     status?: AppointmentStatus,
     *     notes?: string|null,
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    public function handle(array $data): Appointment
    {
        $serviceIds = array_values(array_unique(array_map('intval', $data['service_ids'])));

        if ($serviceIds === []) {
            throw new SlotUnavailableException('No services were selected.');
        }

        $timezone = Setting::current()->timezone;
        $start = $data['start'] instanceof Carbon
            ? $data['start']->copy()->setTimezone($timezone)
            : Carbon::parse($data['start'], $timezone);

        $date = $start->toDateString();
        $time = $start->format('H:i');

        $client = $this->resolveClient($data);
        $requestedStaffId = isset($data['staff_id']) ? (int) $data['staff_id'] : null;

        return DB::transaction(function () use ($serviceIds, $start, $date, $time, $client, $requestedStaffId, $data): Appointment {
            // Resolve "any staff" to a concrete candidate for the requested time.
            $staffId = $requestedStaffId ?? $this->resolveAnyStaff($serviceIds, $date, $time);

            // Serialize concurrent bookings for this staff member (ADR-0007).
            /** @var Staff|null $staff */
            $staff = Staff::query()->whereKey($staffId)->lockForUpdate()->first();

            if ($staff === null) {
                throw new SlotUnavailableException('The selected staff member is unavailable.');
            }

            // Authoritative availability re-check for this specific staff.
            if ($this->slotFor($serviceIds, $staff->id, $date, $time) === null) {
                throw new SlotUnavailableException('The selected time is no longer available.');
            }

            $services = Service::query()->active()->whereKey($serviceIds)->get();
            $duration = (int) $services->sum('duration_minutes');
            $priceTotal = $services->sum(fn (Service $service): float => (float) $service->price);

            $appointment = Appointment::query()->create([
                'client_id' => $client->id,
                'staff_id' => $staff->id,
                'datetime_start' => $start,
                'datetime_end' => $start->copy()->addMinutes($duration),
                'status' => $data['status'] ?? AppointmentStatus::Pending,
                'source' => $data['source'] ?? AppointmentSource::Manual,
                'price_snapshot' => $priceTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($services as $service) {
                $appointment->appointmentServices()->create([
                    'service_id' => $service->id,
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                ]);
            }

            Log::info('appointment.booked', [
                'appointment_id' => $appointment->id,
                'client_id' => $client->id,
                'staff_id' => $staff->id,
                'start' => $start->toDateTimeString(),
                'source' => $appointment->source->value,
                'status' => $appointment->status->value,
            ]);

            return $appointment;
        });
    }

    /**
     * Find or create the client for this booking from validated input.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            return Client::query()->findOrFail((int) $data['client_id']);
        }

        $client = $data['client'] ?? null;

        if (! is_array($client) || empty($client['name']) || empty($client['phone'])) {
            throw new SlotUnavailableException('Client details are required to book.');
        }

        $phone = PhoneNumber::normalize((string) $client['phone']);

        return Client::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => $client['name'], 'email' => $client['email'] ?? null],
        );
    }

    /**
     * The earliest-id staff free at the requested time when "any" was chosen.
     *
     * @param  array<int, int>  $serviceIds
     *
     * @throws SlotUnavailableException
     */
    private function resolveAnyStaff(array $serviceIds, string $date, string $time): int
    {
        $slot = $this->slotFor($serviceIds, null, $date, $time);

        if ($slot === null) {
            throw new SlotUnavailableException('No staff member is available at the selected time.');
        }

        return $slot['staff_id'];
    }

    /**
     * The availability slot matching the requested "H:i" time, or null.
     *
     * @param  array<int, int>  $serviceIds
     * @return array{time: string, datetime: string, staff_id: int, staff_name: string}|null
     */
    private function slotFor(array $serviceIds, ?int $staffId, string $date, string $time): ?array
    {
        foreach ($this->availability->availableSlots($serviceIds, $staffId, $date) as $slot) {
            if ($slot['time'] === $time) {
                return $slot;
            }
        }

        return null;
    }
}
