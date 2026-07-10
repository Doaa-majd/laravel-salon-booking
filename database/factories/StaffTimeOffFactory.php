<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTimeOff>
 */
class StaffTimeOffFactory extends Factory
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
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(3),
        ];
    }

    /**
     * Indicate a partial-day time off with a specific window.
     */
    public function partial(string $start = '12:00', string $end = '14:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
