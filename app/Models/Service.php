<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property int $duration_minutes
 * @property string $price
 * @property string|null $description
 * @property string|null $color
 * @property bool $is_active
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'duration_minutes',
        'price',
        'description',
        'color',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_services');
    }

    /**
     * @return HasMany<AppointmentService, $this>
     */
    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    /**
     * Scope to only active services.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
