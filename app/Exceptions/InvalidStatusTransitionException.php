<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use RuntimeException;

/**
 * Thrown when an appointment status change is not permitted by the transition
 * rules on AppointmentStatus (e.g. reopening a completed appointment).
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(
        public readonly AppointmentStatus $from,
        public readonly AppointmentStatus $to,
    ) {
        parent::__construct("Cannot transition appointment from {$from->value} to {$to->value}.");
    }
}
