<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_name' => fake()->company(),
            'logo' => null,
            'primary_color' => fake()->hexColor(),
            'currency' => Currency::SAR,
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'phone' => '+9665'.fake()->numerify('########'),
            'whatsapp_number' => '+9665'.fake()->numerify('########'),
            'address' => fake()->address(),
            'working_hours' => $this->defaultWorkingHours(),
            'reminder_template' => 'مرحباً {client}، نذكّرك بموعدك في {salon} يوم {date} الساعة {time}.',
            'license_domain' => null,
        ];
    }

    /**
     * Build a default 7-day working-hours structure keyed by weekday (0 = Sunday).
     *
     * @return array<int, array{open: string, close: string, closed: bool}>
     */
    protected function defaultWorkingHours(): array
    {
        $hours = [];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $hours[$weekday] = [
                'open' => '10:00',
                'close' => '22:00',
                'closed' => $weekday === 5, // Friday closed
            ];
        }

        return $hours;
    }
}
