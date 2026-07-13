<?php

namespace App\Concerns;

use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ClientValidationRules
{
    /**
     * Validation rules for the client form.
     *
     * The unique phone constraint counts soft-deleted rows too, so a phone
     * held by a soft-deleted client stays reserved (reuse-on-restore per
     * ADR-0005). `$this->editingId` is ignored on update.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function clientRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:20',
                new PhoneNumber,
                Rule::unique('clients', 'phone')->ignore($this->editingId),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'skin_type' => ['nullable', 'string', 'max:50'],
            'hair_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
