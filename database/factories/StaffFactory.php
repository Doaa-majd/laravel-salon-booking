<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name('female'),
            'phone' => '+9665'.fake()->numerify('########'),
            'photo' => null,
            'bio' => fake()->optional()->sentence(),
            'commission_percent' => fake()->randomFloat(2, 0, 30),
            'is_active' => true,
        ];
    }
}
