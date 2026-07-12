<?php

namespace App\Actions\Services;

use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

class ReorderServiceCategories
{
    /**
     * Persist a new category order, assigning contiguous sort_order
     * values (0, 1, 2, ...) in the order the IDs are given.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function handle(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                ServiceCategory::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $position]);
            }
        });
    }
}
