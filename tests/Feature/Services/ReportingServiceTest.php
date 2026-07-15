<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Services\ReportingService;
use Illuminate\Support\Carbon;

const REP_TZ = 'Asia/Riyadh';

function reporting(): ReportingService
{
    return app(ReportingService::class);
}

/**
 * Create an appointment on $date snapshotting each given service's current
 * price/duration into appointment_services (and price_snapshot as their sum).
 *
 * @param  array<int, Service>  $services
 */
function repAppointment(Staff $staff, array $services, string $date, AppointmentStatus $status): Appointment
{
    $start = Carbon::parse($date.' 12:00', REP_TZ);
    $total = array_sum(array_map(fn (Service $s) => (float) $s->price, $services));

    $appointment = Appointment::factory()->create([
        'client_id' => Client::factory(),
        'staff_id' => $staff->id,
        'datetime_start' => $start,
        'datetime_end' => $start->copy()->addHour(),
        'status' => $status,
        'price_snapshot' => $total,
    ]);

    foreach ($services as $service) {
        $appointment->appointmentServices()->create([
            'service_id' => $service->id,
            'price' => $service->price,
            'duration_minutes' => $service->duration_minutes,
        ]);
    }

    return $appointment;
}

test('top services ranks completed bookings by count and sums snapshot revenue', function () {
    $staff = Staff::factory()->create();
    $haircut = Service::factory()->create(['name' => 'Haircut', 'price' => 100]);
    $color = Service::factory()->create(['name' => 'Color', 'price' => 200]);

    // Haircut appears in 3 completed appointments, color in 1.
    repAppointment($staff, [$haircut], '2026-07-05', AppointmentStatus::Completed);
    repAppointment($staff, [$haircut], '2026-07-06', AppointmentStatus::Completed);
    repAppointment($staff, [$haircut, $color], '2026-07-07', AppointmentStatus::Completed);

    $rows = reporting()->topServices('2026-07-01', '2026-07-31');

    expect($rows)->toHaveCount(2);
    expect($rows[0]['name'])->toBe('Haircut');
    expect($rows[0]['bookings'])->toBe(3);
    expect($rows[0]['revenue'])->toBe(300.0);
    expect($rows[1]['name'])->toBe('Color');
    expect($rows[1]['bookings'])->toBe(1);
    expect($rows[1]['revenue'])->toBe(200.0);
});

test('reports exclude non-completed and out-of-range appointments', function () {
    $staff = Staff::factory()->create();
    $service = Service::factory()->create(['price' => 100]);

    repAppointment($staff, [$service], '2026-07-10', AppointmentStatus::Completed);   // counted
    repAppointment($staff, [$service], '2026-07-10', AppointmentStatus::Pending);     // not completed
    repAppointment($staff, [$service], '2026-07-10', AppointmentStatus::Cancelled);   // not completed
    repAppointment($staff, [$service], '2026-06-30', AppointmentStatus::Completed);   // before range
    repAppointment($staff, [$service], '2026-08-01', AppointmentStatus::Completed);   // after range

    $totals = reporting()->totals('2026-07-01', '2026-07-31');

    expect($totals['completed_count'])->toBe(1);
    expect($totals['revenue'])->toBe(100.0);
});

test('date range boundaries are inclusive', function () {
    $staff = Staff::factory()->create();
    $service = Service::factory()->create(['price' => 50]);

    repAppointment($staff, [$service], '2026-07-01', AppointmentStatus::Completed); // from boundary
    repAppointment($staff, [$service], '2026-07-31', AppointmentStatus::Completed); // to boundary

    expect(reporting()->totals('2026-07-01', '2026-07-31')['completed_count'])->toBe(2);
});

test('staff performance computes completed count, revenue, and commission from snapshots', function () {
    $alya = Staff::factory()->create(['name' => 'Alya', 'commission_percent' => 10]);
    $noor = Staff::factory()->create(['name' => 'Noor', 'commission_percent' => 20]);
    $service = Service::factory()->create(['price' => 150]);

    // Alya: 2 completed (revenue 300, commission 30). Noor: 1 completed (revenue 150, commission 30).
    repAppointment($alya, [$service], '2026-07-05', AppointmentStatus::Completed);
    repAppointment($alya, [$service], '2026-07-06', AppointmentStatus::Completed);
    repAppointment($noor, [$service], '2026-07-07', AppointmentStatus::Completed);

    $rows = collect(reporting()->staffPerformance('2026-07-01', '2026-07-31'))->keyBy('name');

    expect($rows['Alya']['completed_count'])->toBe(2);
    expect($rows['Alya']['revenue'])->toBe(300.0);
    expect($rows['Alya']['commission'])->toBe(30.0);
    expect($rows['Noor']['completed_count'])->toBe(1);
    expect($rows['Noor']['revenue'])->toBe(150.0);
    expect($rows['Noor']['commission'])->toBe(30.0);
});

test('staff with no completed work appears with zeroed figures', function () {
    Staff::factory()->create(['name' => 'Idle', 'commission_percent' => 15]);

    $rows = collect(reporting()->staffPerformance('2026-07-01', '2026-07-31'))->keyBy('name');

    expect($rows['Idle']['completed_count'])->toBe(0);
    expect($rows['Idle']['revenue'])->toBe(0.0);
    expect($rows['Idle']['commission'])->toBe(0.0);
});

test('revenue and commission use the snapshot, not the live service price', function () {
    $staff = Staff::factory()->create(['name' => 'Snap', 'commission_percent' => 10]);
    $service = Service::factory()->create(['price' => 100]);

    repAppointment($staff, [$service], '2026-07-10', AppointmentStatus::Completed);

    // Raise the live price after booking; historical revenue must not move.
    $service->update(['price' => 999]);

    $rows = collect(reporting()->staffPerformance('2026-07-01', '2026-07-31'))->keyBy('name');

    expect($rows['Snap']['revenue'])->toBe(100.0);
    expect($rows['Snap']['commission'])->toBe(10.0);
    expect(reporting()->topServices('2026-07-01', '2026-07-31')[0]['revenue'])->toBe(100.0);
});

test('no-show count reflects only no-shows in range', function () {
    $staff = Staff::factory()->create();
    $service = Service::factory()->create(['price' => 100]);

    repAppointment($staff, [$service], '2026-07-10', AppointmentStatus::NoShow);
    repAppointment($staff, [$service], '2026-07-11', AppointmentStatus::NoShow);
    repAppointment($staff, [$service], '2026-07-12', AppointmentStatus::Completed);
    repAppointment($staff, [$service], '2026-06-01', AppointmentStatus::NoShow); // out of range

    expect(reporting()->noShowCount('2026-07-01', '2026-07-31'))->toBe(2);
    expect(reporting()->totals('2026-07-01', '2026-07-31')['no_shows'])->toBe(2);
});

test('totals sum revenue and commission across staff', function () {
    $a = Staff::factory()->create(['commission_percent' => 10]);
    $b = Staff::factory()->create(['commission_percent' => 50]);
    $service = Service::factory()->create(['price' => 200]);

    repAppointment($a, [$service], '2026-07-05', AppointmentStatus::Completed); // rev 200, comm 20
    repAppointment($b, [$service], '2026-07-06', AppointmentStatus::Completed); // rev 200, comm 100

    $totals = reporting()->totals('2026-07-01', '2026-07-31');

    expect($totals['completed_count'])->toBe(2);
    expect($totals['revenue'])->toBe(400.0);
    expect($totals['commission'])->toBe(120.0);
});
