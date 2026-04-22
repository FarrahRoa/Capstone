<?php

namespace App\Contracts;

use App\Models\Reservation;

interface ReservationCloudPushGatewayContract
{
    /**
     * Push a single reservation snapshot to the primary cloud.
     *
     * @return array{ok: bool, http_status: ?int, duplicate: bool, message: string}
     */
    public function pushReservation(Reservation $reservation): array;
}
