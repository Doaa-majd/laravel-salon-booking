<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use Database\Factories\ReminderLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $appointment_id
 * @property ReminderChannel $channel
 * @property Carbon $sent_at
 * @property int|null $sent_by
 */
class ReminderLog extends Model
{
    /** @use HasFactory<ReminderLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'channel',
        'sent_at',
        'sent_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ReminderChannel::class,
            'sent_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
