<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $salon_name
 * @property string|null $logo
 * @property string|null $primary_color
 * @property Currency $currency
 * @property string $timezone
 * @property int $week_start
 * @property string|null $phone
 * @property string|null $whatsapp_number
 * @property string|null $address
 * @property array<int, array{open: string, close: string, closed: bool}> $working_hours
 * @property string|null $reminder_template
 * @property string|null $license_domain
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'salon_name',
        'logo',
        'primary_color',
        'currency',
        'timezone',
        'week_start',
        'phone',
        'whatsapp_number',
        'address',
        'working_hours',
        'reminder_template',
        'license_domain',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'week_start' => 'integer',
            'working_hours' => 'array',
        ];
    }

    /**
     * Retrieve the single salon settings row, creating it with defaults if absent.
     */
    public static function current(): self
    {
        $setting = static::query()->orderBy('id')->first();

        if ($setting instanceof self) {
            return $setting;
        }

        return static::query()->create([
            'salon_name' => (string) config('app.name', 'Salon'),
            'currency' => Currency::SAR,
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_hours' => self::defaultWorkingHours(),
        ]);
    }

    /**
     * Default 7-day working-hours structure (0 = Sunday, Friday closed).
     *
     * @return array<int, array{open: string, close: string, closed: bool}>
     */
    public static function defaultWorkingHours(): array
    {
        $hours = [];

        foreach (range(0, 6) as $weekday) {
            $hours[$weekday] = [
                'open' => '10:00',
                'close' => '22:00',
                'closed' => $weekday === 5,
            ];
        }

        return $hours;
    }
}
