<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class ReservationVerificationMailException extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly int $spaceId,
        public readonly string $startAt,
        public readonly string $endAt,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Reservation verification email send failed.', 0, $previous);
    }
}

