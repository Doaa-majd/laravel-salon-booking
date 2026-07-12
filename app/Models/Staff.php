<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $photo
 * @property string|null $bio
 * @property string $commission_percent
 * @property bool $is_active
 */
class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'name',
        'phone',
        'photo',
        'bio',
        'commission_percent',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services');
    }

    /**
     * @return HasMany<StaffSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    /**
     * @return HasMany<StaffTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The user account linked to this staff member, if any.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Scope to only active staff.
     *
     * @param  Builder<Staff>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to staff who provide every one of the given services.
     *
     * Used by the availability engine to filter candidate staff for a
     * multi-service booking. Soft-deleted services are excluded via the
     * relation's default scope.
     *
     * @param  Builder<Staff>  $query
     * @param  array<int, int>  $serviceIds
     */
    public function scopeProvidesAllServices(Builder $query, array $serviceIds): void
    {
        foreach (array_unique($serviceIds) as $serviceId) {
            $query->whereHas('services', function (Builder $services) use ($serviceId): void {
                $services->whereKey($serviceId);
            });
        }
    }
}
