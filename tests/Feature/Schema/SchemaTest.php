<?php

use Illuminate\Support\Facades\Schema;

test('all domain tables are created', function () {
    $tables = [
        'settings',
        'service_categories',
        'services',
        'staff',
        'staff_services',
        'staff_schedules',
        'staff_time_off',
        'clients',
        'appointments',
        'appointment_services',
        'client_photos',
        'reminder_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table [{$table}]");
    }
});

test('users table has role and staff_id columns', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue();
    expect(Schema::hasColumn('users', 'staff_id'))->toBeTrue();
});

test('soft-delete columns exist on historical tables', function () {
    foreach (['services', 'staff', 'clients', 'appointments'] as $table) {
        expect(Schema::hasColumn($table, 'deleted_at'))->toBeTrue("Missing deleted_at on [{$table}]");
    }
});
