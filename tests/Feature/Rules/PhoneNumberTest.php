<?php

use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;

test('accepts canonical Saudi and Emirati numbers', function (string $phone) {
    $validator = Validator::make(['phone' => $phone], ['phone' => [new PhoneNumber]]);

    expect($validator->passes())->toBeTrue();
})->with([
    '+966500000001',
    '+971500000001',
    '+96650000001',
]);

test('rejects malformed numbers', function (string $phone) {
    $validator = Validator::make(['phone' => $phone], ['phone' => [new PhoneNumber]]);

    expect($validator->fails())->toBeTrue();
})->with([
    '0500000001',
    '+9995000000',
    '+966',
    'not-a-number',
]);

test('normalize strips separators and converts a leading double-zero', function () {
    expect(PhoneNumber::normalize('+966 50-000 0001'))->toBe('+966500000001');
    expect(PhoneNumber::normalize('00966500000001'))->toBe('+966500000001');
    expect(PhoneNumber::normalize(' (971) 50 000 0001 '))->toBe('971500000001');
});

test('normalized separated input passes validation', function () {
    $validator = Validator::make(
        ['phone' => PhoneNumber::normalize('+966 50 000 0001')],
        ['phone' => [new PhoneNumber]],
    );

    expect($validator->passes())->toBeTrue();
});
