<?php

namespace Database\Factories;

use App\Enums\ReminderChannel;
use App\Models\Appointment;
use App\Models\ReminderLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderLog>
 */
class ReminderLogFactory extends Factory
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
            'channel' => ReminderChannel::Whatsapp,
            'sent_at' => now(),
            'sent_by' => null,
        ];
    }
}
