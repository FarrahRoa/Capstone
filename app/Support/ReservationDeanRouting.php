<?php

namespace App\Support;

use App\Mail\Reservation\ReservationPendingApprovalAdminMail;
use App\Models\DeanEmailMapping;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class ReservationDeanRouting
{
    /**
     * Office name in dean_email_mappings (affiliation_type office_department) for
     * AVR/Lobby reservations when the requester selects an organization event.
     */
    public const ORGANIZATION_DEAN_AFFILIATION_NAME = 'SACDEV';

    public static function spaceUsesAvrLobbyAudienceRouting(Space $space): bool
    {
        return in_array((string) $space->type, ['avr', 'lobby'], true)
            || in_array((string) $space->slug, ['avr', 'lobby'], true);
    }

    public static function activeMappingForOrganizationAvrLobby(): ?DeanEmailMapping
    {
        return DeanEmailMapping::query()
            ->where('is_active', true)
            ->where('affiliation_type', DeanEmailMapping::TYPE_OFFICE_DEPARTMENT)
            ->where('affiliation_name', self::ORGANIZATION_DEAN_AFFILIATION_NAME)
            ->first();
    }

    public static function activeMappingForUserAffiliation(User $user): ?DeanEmailMapping
    {
        $unit = trim((string) ($user->college_office ?? ''));
        if ($unit === '') {
            return null;
        }
        $userType = $user->user_type ?? User::getUserTypeFromEmail((string) $user->email);
        $affiliationType = $userType === User::USER_TYPE_STUDENT
            ? DeanEmailMapping::TYPE_COLLEGE
            : DeanEmailMapping::TYPE_OFFICE_DEPARTMENT;

        return DeanEmailMapping::query()
            ->where('is_active', true)
            ->where('affiliation_type', $affiliationType)
            ->where('affiliation_name', $unit)
            ->first();
    }

    public static function resolveActiveDeanMapping(Reservation $reservation): ?DeanEmailMapping
    {
        $reservation->loadMissing('space', 'user');
        if (! $reservation->space || ! self::spaceUsesAvrLobbyAudienceRouting($reservation->space)) {
            return null;
        }

        return match ($reservation->event_request_type) {
            Reservation::EVENT_REQUEST_ORGANIZATION => self::activeMappingForOrganizationAvrLobby(),
            Reservation::EVENT_REQUEST_EMPLOYEE => $reservation->user
                ? self::activeMappingForUserAffiliation($reservation->user)
                : null,
            default => null,
        };
    }

    /**
     * @param  ?string  $eventRequestType  raw request value; null/'' when not applicable
     */
    public static function assertAudienceAndDeanMappingForReservation(
        Space $space,
        User $user,
        ?string $eventRequestType,
    ): void {
        $trimmed = $eventRequestType !== null ? trim($eventRequestType) : '';

        if (! self::spaceUsesAvrLobbyAudienceRouting($space)) {
            if ($trimmed !== '') {
                throw ValidationException::withMessages([
                    'event_request_type' => ['Event audience applies only to AVR and Lobby reservations.'],
                ]);
            }

            return;
        }

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'event_request_type' => ['Select whether this is an organization event or an employee event.'],
            ]);
        }

        if (! in_array($trimmed, [Reservation::EVENT_REQUEST_ORGANIZATION, Reservation::EVENT_REQUEST_EMPLOYEE], true)) {
            throw ValidationException::withMessages([
                'event_request_type' => ['Invalid event audience.'],
            ]);
        }

        $tmp = new Reservation([
            'event_request_type' => $trimmed,
            'user_id' => $user->id,
        ]);
        $tmp->setRelation('user', $user);
        $tmp->setRelation('space', $space);

        $mapping = self::resolveActiveDeanMapping($tmp);
        if ($mapping !== null) {
            return;
        }

        if ($trimmed === Reservation::EVENT_REQUEST_ORGANIZATION) {
            throw ValidationException::withMessages([
                'event_request_type' => [
                    'No active dean approver is configured for SACDEV. Ask an administrator to add a dean email mapping for the SACDEV office.',
                ],
            ]);
        }

        $unit = trim((string) ($user->college_office ?? ''));
        if ($unit === '') {
            throw ValidationException::withMessages([
                'event_request_type' => [
                    'Complete your profile with your college or office before reserving for an employee event.',
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'event_request_type' => [
                "No active dean approver is configured for «{$unit}». Ask an administrator to add a dean email mapping for this affiliation.",
            ],
        ]);
    }

    /**
     * @return array<string, string> normalized lowercase email => send address
     */
    public static function pendingApprovalRecipientEmails(Reservation $reservation): array
    {
        $reservation->loadMissing('space', 'user');
        $out = [];
        foreach (User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))->cursor() as $admin) {
            $e = trim((string) $admin->email);
            if ($e !== '') {
                $out[strtolower($e)] = $e;
            }
        }

        if (self::spaceUsesAvrLobbyAudienceRouting($reservation->space)) {
            $mapping = self::resolveActiveDeanMapping($reservation);
            if ($mapping !== null) {
                $e = trim((string) $mapping->approver_email);
                if ($e !== '') {
                    $out[strtolower($e)] = $e;
                }
            }
        }

        return $out;
    }

    public static function sendPendingApprovalNotifications(Reservation $reservation): void
    {
        foreach (self::pendingApprovalRecipientEmails($reservation) as $email) {
            Mail::to($email)->send(new ReservationPendingApprovalAdminMail($reservation));
        }
    }
}
