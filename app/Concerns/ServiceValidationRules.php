<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ServiceValidationRules
{
    /**
     * Validation rules for the service form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function serviceRules(): array
    {
        return [
            'category_id' => ['nullable', Rule::exists('service_categories', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ];
    }
}
