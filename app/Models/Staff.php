<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Scope to only active staff.
     *
     * @param  Builder<Staff>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
