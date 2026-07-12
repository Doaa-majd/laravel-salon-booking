<?php

namespace App\Concerns;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

trait StaffScheduleValidationRules
{
    /**
     * Validation rules for the 7-day weekly schedule grid.
     *
     * Every open day (is_off = false) must have a start/end in H:i format
     * with the end strictly after the start. Days off keep their (ignored)
     * times, so the fixed 7-row shape guarantees one row per weekday.
     *
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    protected function scheduleRules(): array
    {
        return [
            'schedule' => ['array', 'size:7'],
            'schedule.*.start_time' => ['required', 'date_format:H:i'],
            'schedule.*.is_off' => ['boolean'],
            'schedule.*.end_time' => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $weekday = (int) explode('.', $attribute)[1];

                    /** @var array{start_time?: string, is_off?: bool}|null $row */
                    $row = $this->schedule[$weekday] ?? null;

                    if ($row === null || ($row['is_off'] ?? false)) {
                        return;
                    }

                    if (is_string($value) && $value <= ($row['start_time'] ?? '')) {
                        $fail(__('End time must be after start time.'));
                    }
                },
            ],
        ];
    }

    /**
     * Validation rules for adding a specific-date time-off entry.
     *
     * Times are optional (empty start/end = a full day off). When either
     * time is given both are required and the end must be after the start.
     * Leaves cannot be recorded for a past date.
     *
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    protected function timeOffRules(): array
    {
        return [
            'timeOffDate' => ['required', 'date', 'after_or_equal:today'],
            'timeOffStart' => ['nullable', 'date_format:H:i', 'required_with:timeOffEnd'],
            'timeOffEnd' => [
                'nullable',
                'date_format:H:i',
                'required_with:timeOffStart',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && is_string($this->timeOffStart) && $value <= $this->timeOffStart) {
                        $fail(__('End time must be after start time.'));
                    }
                },
            ],
            'timeOffReason' => ['nullable', 'string', 'max:150'],
        ];
    }
}
