<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'phone' => '+9665'.fake()->unique()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
            'birthdate' => fake()->optional()->date(),
            'skin_type' => fake()->optional()->randomElement(['normal', 'dry', 'oily', 'combination']),
            'hair_type' => fake()->optional()->randomElement(['straight', 'wavy', 'curly']),
            'notes' => fake()->optional()->sentence(),
            'tags' => fake()->optional()->randomElements(['VIP', 'حساسية', 'دائم'], 2),
        ];
    }
}
