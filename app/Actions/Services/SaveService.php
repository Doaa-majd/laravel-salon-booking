<?php

namespace App\Actions\Services;

use App\Models\Service;

class SaveService
{
    /**
     * Create or update a service from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?Service $service, array $data): Service
    {
        $service ??= new Service;

        $service->fill([
            'category_id' => $data['category_id'] ?: null,
            'name' => $data['name'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'price' => $data['price'],
            'description' => $data['description'] ?: null,
            'color' => $data['color'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $service->save();

        return $service;
    }
}
