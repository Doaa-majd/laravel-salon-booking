<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Authorization for appointments. Admins may see and manage every appointment;
 * a staff-role user may only see and manage appointments assigned to their own
 * linked staff profile. The admin area is additionally gated by the `admin`
 * middleware, but this policy is the reusable ownership rule for staff-facing
 * views (e.g. the Day 10 staff calendar).
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->owns($user, $appointment);
    }

    /**
     * Admins own every appointment; staff own only their own assignments.
     */
    private function owns(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->staff_id !== null && $user->staff_id === $appointment->staff_id;
    }
}
