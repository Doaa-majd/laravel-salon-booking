<?php

namespace App\Actions\Clients;

use App\Models\Client;

class SaveClient
{
    /**
     * Create or update a client from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?Client $client, array $data): Client
    {
        $client ??= new Client;

        /** @var array<int, string> $tags */
        $tags = array_values(array_unique(array_filter($data['tags'] ?? [])));

        $client->fill([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?: null,
            'birthdate' => $data['birthdate'] ?: null,
            'skin_type' => $data['skin_type'] ?: null,
            'hair_type' => $data['hair_type'] ?: null,
            'notes' => $data['notes'] ?: null,
            'tags' => $tags ?: null,
        ]);

        $client->save();

        return $client;
    }
}
