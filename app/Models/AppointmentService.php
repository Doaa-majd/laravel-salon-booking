<?php

namespace App\Models;

use Database\Factories\AppointmentServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int $service_id
 * @property string $price
 * @property int $duration_minutes
 */
class AppointmentService extends Model
{
    /** @use HasFactory<AppointmentServiceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'service_id',
        'price',
        'duration_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
