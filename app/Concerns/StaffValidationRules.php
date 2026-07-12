<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait StaffValidationRules
{
    /**
     * Validation rules for the staff form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function staffRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'service_ids' => ['array'],
            'service_ids.*' => [Rule::exists('services', 'id')],
        ];
    }
}
