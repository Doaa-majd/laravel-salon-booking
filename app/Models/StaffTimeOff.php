<?php

namespace App\Models;

use Database\Factories\StaffTimeOffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $staff_id
 * @property Carbon $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 */
class StaffTimeOff extends Model
{
    /** @use HasFactory<StaffTimeOffFactory> */
    use HasFactory;

    protected $table = 'staff_time_off';

    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'date',
        'start_time',
        'end_time',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
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
