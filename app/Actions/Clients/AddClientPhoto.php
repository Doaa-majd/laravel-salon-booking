<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\ClientPhoto;

class AddClientPhoto
{
    /**
     * Attach a before/after photo to a client, optionally linked to one of
     * their appointments.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): ClientPhoto
    {
        return $client->photos()->create([
            'appointment_id' => $data['appointment_id'] ?: null,
            'type' => $data['type'],
            'image_path' => $data['image_path'],
            'note' => $data['note'] ?: null,
            'taken_at' => $data['taken_at'] ?: now(),
        ]);
    }
}
