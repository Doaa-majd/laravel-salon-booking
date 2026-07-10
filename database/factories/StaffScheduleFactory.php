<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffSchedule>
 */
class StaffScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'is_off' => false,
        ];
    }

    /**
     * Indicate that the schedule day is a weekly day off.
     */
    public function off(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_off' => true,
        ]);
    }
}
