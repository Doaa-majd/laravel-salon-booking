<?php

use App\Livewire\Admin\StaffSchedule as StaffScheduleComponent;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Build the full 7-day schedule payload the component validates, letting a
 * test override individual weekdays.
 *
 * @param  array<int, array<string, mixed>>  $overrides
 * @return array<int, array{start_time: string, end_time: string, is_off: bool}>
 */
function scheduleGrid(array $overrides = []): array
{
    $grid = [];

    for ($weekday = 0; $weekday <= 6; $weekday++) {
        $grid[$weekday] = array_merge(
            ['start_time' => '10:00', 'end_time' => '18:00', 'is_off' => false],
            $overrides[$weekday] ?? [],
        );
    }

    return $grid;
}

test('admin can view the staff schedule page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    $this->get(route('admin.staff.schedule', $staff))->assertOk();
});

test('staff cannot view the staff schedule page', function () {
    $this->actingAs(User::factory()->create());

    $staff = Staff::factory()->create();

    $this->get(route('admin.staff.schedule', $staff))->assertForbidden();
});

test('guests are redirected from the staff schedule page to login', function () {
    $staff = Staff::factory()->create();

    $this->get(route('admin.staff.schedule', $staff))->assertRedirect(route('login'));
});

test('the schedule editor loads existing rows and defaults missing days', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();
    StaffSchedule::factory()->create([
        'staff_id' => $staff->id,
        'weekday' => 2,
        'start_time' => '09:30',
        'end_time' => '17:00',
        'is_off' => false,
    ]);

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->assertSet('schedule.2.start_time', '09:30')
        ->assertSet('schedule.2.end_time', '17:00')
        ->assertSet('schedule.2.is_off', false)
        // A weekday with no stored row falls back to defaults.
        ->assertSet('schedule.0.start_time', '10:00')
        ->assertSet('schedule.0.end_time', '18:00');
});

test('admin can save a weekly schedule', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('schedule', scheduleGrid([
            5 => ['is_off' => true],
            1 => ['start_time' => '11:00', 'end_time' => '19:00'],
        ]))
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect($staff->schedules()->count())->toBe(7);

    $monday = $staff->schedules()->where('weekday', 1)->firstOrFail();
    expect(substr($monday->start_time, 0, 5))->toBe('11:00');
    expect(substr($monday->end_time, 0, 5))->toBe('19:00');

    $friday = $staff->schedules()->where('weekday', 5)->firstOrFail();
    expect($friday->is_off)->toBeTrue();
});

test('saving the schedule twice keeps one row per weekday', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    $component = Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('schedule', scheduleGrid())
        ->call('saveSchedule')
        ->assertHasNoErrors();

    $component
        ->set('schedule', scheduleGrid([3 => ['start_time' => '08:00', 'end_time' => '12:00']]))
        ->call('saveSchedule')
        ->assertHasNoErrors();

    // Unique (staff_id, weekday) upsert — still exactly 7 rows, updated in place.
    expect($staff->schedules()->count())->toBe(7);
    expect($staff->schedules()->where('weekday', 3)->count())->toBe(1);
    expect(substr($staff->schedules()->where('weekday', 3)->value('start_time'), 0, 5))->toBe('08:00');
});

test('an open day requires the end time to be after the start time', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('schedule', scheduleGrid([
            2 => ['start_time' => '18:00', 'end_time' => '10:00'],
        ]))
        ->call('saveSchedule')
        ->assertHasErrors('schedule.2.end_time');

    expect($staff->schedules()->count())->toBe(0);
});

test('a day off skips the end-after-start check', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    // Times are inverted, but is_off means they are ignored.
    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('schedule', scheduleGrid([
            2 => ['start_time' => '18:00', 'end_time' => '10:00', 'is_off' => true],
        ]))
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect($staff->schedules()->where('weekday', 2)->value('is_off'))->toBeTrue();
});

test('admin can add a full-day time off', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();
    $date = Carbon::tomorrow()->format('Y-m-d');

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('timeOffDate', $date)
        ->set('timeOffReason', 'Vacation')
        ->call('addTimeOff')
        ->assertHasNoErrors()
        ->assertSet('timeOffDate', '');

    $entry = $staff->timeOff()->firstOrFail();
    expect($entry->start_time)->toBeNull();
    expect($entry->end_time)->toBeNull();
    expect($entry->reason)->toBe('Vacation');
});

test('admin can add a partial-day time off', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('timeOffDate', Carbon::tomorrow()->format('Y-m-d'))
        ->set('timeOffStart', '12:00')
        ->set('timeOffEnd', '14:00')
        ->call('addTimeOff')
        ->assertHasNoErrors();

    $entry = $staff->timeOff()->firstOrFail();
    expect(substr($entry->start_time, 0, 5))->toBe('12:00');
    expect(substr($entry->end_time, 0, 5))->toBe('14:00');
});

test('time off cannot be recorded for a past date', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('timeOffDate', Carbon::yesterday()->format('Y-m-d'))
        ->call('addTimeOff')
        ->assertHasErrors('timeOffDate');

    expect($staff->timeOff()->count())->toBe(0);
});

test('a partial time off end must be after its start', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('timeOffDate', Carbon::tomorrow()->format('Y-m-d'))
        ->set('timeOffStart', '15:00')
        ->set('timeOffEnd', '13:00')
        ->call('addTimeOff')
        ->assertHasErrors('timeOffEnd');

    expect($staff->timeOff()->count())->toBe(0);
});

test('providing only one partial time off bound is rejected', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->set('timeOffDate', Carbon::tomorrow()->format('Y-m-d'))
        ->set('timeOffStart', '12:00')
        ->call('addTimeOff')
        ->assertHasErrors('timeOffEnd');
});

test('admin can remove a time off entry', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();
    $entry = StaffTimeOff::factory()->create(['staff_id' => $staff->id]);

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->call('removeTimeOff', $entry->id)
        ->assertHasNoErrors();

    expect($staff->timeOff()->count())->toBe(0);
});

test('removing time off is scoped to the staff member', function () {
    $this->actingAs(User::factory()->admin()->create());

    $staff = Staff::factory()->create();
    $otherStaff = Staff::factory()->create();
    $otherEntry = StaffTimeOff::factory()->create(['staff_id' => $otherStaff->id]);

    Livewire::test(StaffScheduleComponent::class, ['staff' => $staff])
        ->call('removeTimeOff', $otherEntry->id);

    // Another staff member's leave is untouched.
    expect(StaffTimeOff::query()->find($otherEntry->id))->not->toBeNull();
});
