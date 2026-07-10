<?php

namespace App\Models;

use Database\Factories\StaffScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_off
 */
class StaffSchedule extends Model
{
    /** @use HasFactory<StaffScheduleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'weekday',
        'start_time',
        'end_time',
        'is_off',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_off' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
