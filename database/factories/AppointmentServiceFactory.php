<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentService>
 */
class AppointmentServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'price' => fake()->randomFloat(2, 50, 500),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
        ];
    }
}
