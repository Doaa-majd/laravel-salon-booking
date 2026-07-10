<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property Carbon|null $birthdate
 * @property string|null $skin_type
 * @property string|null $hair_type
 * @property string|null $notes
 * @property array<int, string>|null $tags
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'birthdate',
        'skin_type',
        'hair_type',
        'notes',
        'tags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'tags' => 'array',
        ];
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<ClientPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ClientPhoto::class);
    }
}
