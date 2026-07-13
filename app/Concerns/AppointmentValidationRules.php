<?php

namespace App\Concerns;

use App\Enums\AppointmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AppointmentValidationRules
{
    /**
     * Validation rules for the manual appointment (booking) form.
     *
     * The chosen time is only a candidate here — BookAppointment re-checks it
     * against the availability engine inside a locked transaction, so a slot
     * that filled between selection and submit is rejected there, not here.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function appointmentRules(): array
    {
        return [
            'form_client_id' => ['required', Rule::exists('clients', 'id')],
            'form_service_ids' => ['required', 'array', 'min:1'],
            'form_service_ids.*' => [Rule::exists('services', 'id')],
            'form_staff_id' => ['nullable', Rule::exists('staff', 'id')],
            'form_date' => ['required', 'date'],
            'form_time' => ['required', 'string'],
            'form_status' => ['required', Rule::enum(AppointmentStatus::class)],
            'form_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
