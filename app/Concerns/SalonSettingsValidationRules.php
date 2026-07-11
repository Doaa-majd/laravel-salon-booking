<?php

namespace App\Concerns;

use App\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait SalonSettingsValidationRules
{
    /**
     * Validation rules for the salon settings form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function salonSettingsRules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'timezone' => ['required', 'timezone'],
            'week_start' => ['required', 'integer', 'between:0,6'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'reminder_template' => ['nullable', 'string', 'max:1000'],
            'working_hours' => ['required', 'array'],
            'working_hours.*.open' => ['required', 'date_format:H:i'],
            'working_hours.*.close' => ['required', 'date_format:H:i'],
            'working_hours.*.closed' => ['boolean'],
        ];
    }
}
