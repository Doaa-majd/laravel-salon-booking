<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int $staff_id
 * @property Carbon $datetime_start
 * @property Carbon $datetime_end
 * @property AppointmentStatus $status
 * @property AppointmentSource $source
 * @property string $price_snapshot
 * @property string|null $notes
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'staff_id',
        'datetime_start',
        'datetime_end',
        'status',
        'source',
        'price_snapshot',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datetime_start' => 'datetime',
            'datetime_end' => 'datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'price_snapshot' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<AppointmentService, $this>
     */
    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['price', 'duration_minutes']);
    }

    /**
     * @return HasMany<ClientPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ClientPhoto::class);
    }

    /**
     * @return HasMany<ReminderLog, $this>
     */
    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }
}
