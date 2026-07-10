<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('now', '+2 weeks'))->setTime(fake()->numberBetween(10, 18), 0);
        $duration = fake()->randomElement([30, 60, 90]);

        return [
            'client_id' => Client::factory(),
            'staff_id' => Staff::factory(),
            'datetime_start' => $start,
            'datetime_end' => $start->copy()->addMinutes($duration),
            'status' => AppointmentStatus::Pending,
            'source' => AppointmentSource::Manual,
            'price_snapshot' => fake()->randomFloat(2, 50, 500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
