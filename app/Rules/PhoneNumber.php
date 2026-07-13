<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a salon phone number in canonical Saudi (+966) or Emirati (+971)
 * international form. Shared by the admin client form and the public booking
 * wizard (Day 9) so both flows dedupe clients on an identical phone shape.
 */
class PhoneNumber implements ValidationRule
{
    /**
     * Canonical form: +966 or +971 followed by 8–9 digits.
     */
    public const PATTERN = '/^\+(966|971)[0-9]{8,9}$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, self::normalize($value)) !== 1) {
            $fail(__('The :attribute must be a valid Saudi (+966) or Emirati (+971) phone number.'));
        }
    }

    /**
     * Normalize a user-entered phone number toward the canonical form:
     * strip spaces/dashes/parentheses and convert a leading 00 to +.
     */
    public static function normalize(string $value): string
    {
        $value = preg_replace('/[\s\-()]+/', '', trim($value)) ?? '';

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        return $value;
    }
}
