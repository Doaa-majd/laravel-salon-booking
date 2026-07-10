<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ServiceCategory::factory(),
            'name' => fake()->words(2, true),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price' => fake()->randomFloat(2, 50, 500),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
