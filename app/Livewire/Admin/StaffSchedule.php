<?php

namespace App\Livewire\Admin;

use App\Actions\Staff\AddStaffTimeOff;
use App\Actions\Staff\SaveStaffSchedule;
use App\Concerns\StaffScheduleValidationRules;
use App\Models\Staff as StaffModel;
use App\Models\StaffTimeOff;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Staff schedule')]
class StaffSchedule extends Component
{
    use StaffScheduleValidationRules;

    public StaffModel $staff;

    /**
     * Weekly schedule keyed by weekday (0 = Sunday .. 6 = Saturday).
     *
     * @var array<int, array{start_time: string, end_time: string, is_off: bool}>
     */
    public array $schedule = [];

    public string $timeOffDate = '';

    public ?string $timeOffStart = null;

    public ?string $timeOffEnd = null;

    public ?string $timeOffReason = null;

    public function mount(StaffModel $staff): void
    {
        $this->staff = $staff;
        $this->loadSchedule();
    }

    /**
     * Persist the full weekly schedule.
     */
    public function saveSchedule(SaveStaffSchedule $action): void
    {
        $validated = $this->validate($this->scheduleRules());

        $action->handle($this->staff, $validated['schedule']);

        Flux::toast(variant: 'success', text: __('Weekly schedule saved.'));
    }

    /**
     * Add a specific-date time-off entry.
     */
    public function addTimeOff(AddStaffTimeOff $action): void
    {
        $validated = $this->validate($this->timeOffRules());

        $action->handle($this->staff, $validated);

        $this->reset('timeOffDate', 'timeOffStart', 'timeOffEnd', 'timeOffReason');

        Flux::toast(variant: 'success', text: __('Time off added.'));
    }

    /**
     * Remove a time-off entry belonging to this staff member.
     */
    public function removeTimeOff(int $timeOffId): void
    {
        $this->staff->timeOff()->whereKey($timeOffId)->delete();

        Flux::toast(variant: 'success', text: __('Time off removed.'));
    }

    /**
     * Seed the editable schedule from stored rows, defaulting missing days.
     */
    protected function loadSchedule(): void
    {
        $existing = $this->staff->schedules()->get()->keyBy('weekday');

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            /** @var \App\Models\StaffSchedule|null $row */
            $row = $existing->get($weekday);

            $this->schedule[$weekday] = $row
                ? [
                    'start_time' => substr($row->start_time, 0, 5),
                    'end_time' => substr($row->end_time, 0, 5),
                    'is_off' => $row->is_off,
                ]
                : [
                    'start_time' => '10:00',
                    'end_time' => '18:00',
                    'is_off' => false,
                ];
        }
    }

    /**
     * Upcoming and recent time-off entries, most recent first.
     *
     * @return Collection<int, StaffTimeOff>
     */
    protected function timeOff(): Collection
    {
        return $this->staff->timeOff()->orderByDesc('date')->get();
    }

    /**
     * Localized weekday labels keyed by weekday number (0 = Sunday).
     *
     * @return array<int, string>
     */
    protected function weekdayNames(): array
    {
        return [
            0 => __('Sunday'),
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
        ];
    }

    public function render(): mixed
    {
        return view('livewire.admin.staff-schedule', [
            'timeOff' => $this->timeOff(),
            'weekdayNames' => $this->weekdayNames(),
        ]);
    }
}
