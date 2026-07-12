<?php

namespace App\Actions\Services;

use App\Models\ServiceCategory;

class SaveServiceCategory
{
    /**
     * Create or update a service category from validated data.
     *
     * New categories are appended to the end of the sort order.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?ServiceCategory $category, array $data): ServiceCategory
    {
        $category ??= new ServiceCategory;

        if (! $category->exists) {
            $category->sort_order = (int) (ServiceCategory::query()->max('sort_order') ?? -1) + 1;
        }

        $category->fill([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $category->save();

        return $category;
    }
}
