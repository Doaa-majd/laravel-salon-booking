<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal read-only endpoint backing the public booking wizard (Day 9).
 *
 * GET /api/available-slots?service_ids=1,2&staff_id=3&date=2026-07-13
 *
 * Returns the bookable start times for the requested services on the given
 * date, optionally narrowed to a specific staff member. It performs no writes;
 * the authoritative availability re-check happens inside BookAppointment.
 */
class AvailableSlotsController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'service_ids' => ['required'],
            'staff_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $serviceIds = $this->parseServiceIds($validated['service_ids']);

        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;

        $slots = $availability->availableSlots($serviceIds, $staffId, $validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
        ]);
    }

    /**
     * Accept service ids either as a comma-separated string or an array.
     *
     * @return array<int, int>
     */
    private function parseServiceIds(mixed $value): array
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('intval', $ids)));
    }
}
