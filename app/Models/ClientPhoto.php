<?php

namespace App\Models;

use App\Enums\ClientPhotoType;
use Database\Factories\ClientPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $appointment_id
 * @property ClientPhotoType $type
 * @property string $image_path
 * @property string|null $note
 * @property Carbon $taken_at
 */
class ClientPhoto extends Model
{
    /** @use HasFactory<ClientPhotoFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'appointment_id',
        'type',
        'image_path',
        'note',
        'taken_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientPhotoType::class,
            'taken_at' => 'datetime',
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
