<?php

namespace Database\Factories;

use App\Enums\ClientPhotoType;
use App\Models\Client;
use App\Models\ClientPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPhoto>
 */
class ClientPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'appointment_id' => null,
            'type' => fake()->randomElement(ClientPhotoType::cases()),
            'image_path' => 'client-photos/'.fake()->uuid().'.jpg',
            'note' => fake()->optional()->sentence(3),
            'taken_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
