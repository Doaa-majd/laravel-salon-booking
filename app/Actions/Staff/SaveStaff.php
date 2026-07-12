<?php

namespace App\Actions\Staff;

use App\Models\Staff;

class SaveStaff
{
    /**
     * Create or update a staff member from validated data and sync the
     * services they provide.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?Staff $staff, array $data): Staff
    {
        $staff ??= new Staff;

        $staff->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'bio' => $data['bio'] ?: null,
            'commission_percent' => $data['commission_percent'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if (! empty($data['photo'])) {
            $staff->photo = $data['photo'];
        }

        $staff->save();

        /** @var array<int, int> $serviceIds */
        $serviceIds = $data['service_ids'] ?? [];

        $staff->services()->sync($serviceIds);

        return $staff;
    }
}
