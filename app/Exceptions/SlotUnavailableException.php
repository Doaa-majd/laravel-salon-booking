<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by BookAppointment when the requested start time is no longer bookable
 * for the target staff — either it was never valid or it filled between slot
 * selection and submit (the authoritative availability re-check inside the
 * locked transaction). Callers surface this as a "pick another time" message.
 */
class SlotUnavailableException extends RuntimeException {}
