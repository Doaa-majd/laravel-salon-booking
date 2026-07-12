<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ServiceCategoryValidationRules
{
    /**
     * Validation rules for the service category form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function serviceCategoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
