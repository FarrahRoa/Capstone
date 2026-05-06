<?php

namespace App\Support;

use App\Mail\Reservation\ReservationDeanReviewRequestMail;
use App\Mail\Reservation\ReservationPendingApprovalAdminMail;
use App\Models\Office;
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
        $office = Office::query()->where('name', self::ORGANIZATION_DEAN_AFFILIATION_NAME)->first();
        if (! $office) {
            // Legacy fallback: name-based mapping
            return DeanEmailMapping::query()
                ->where('is_active', true)
                ->where('affiliation_type', DeanEmailMapping::TYPE_OFFICE_DEPARTMENT)
                ->where('affiliation_name', self::ORGANIZATION_DEAN_AFFILIATION_NAME)
                ->first();
        }

        return DeanEmailMapping::query()
            ->where('is_active', true)
            ->where('office_id', $office->id)
            ->first();
    }

    public static function activeMappingForUserAffiliation(User $user): ?DeanEmailMapping
    {
        $userType = $user->user_type ?? User::getUserTypeFromEmail((string) $user->email);
        if ($userType === User::USER_TYPE_STUDENT) {
            if ($user->college_id) {
                return DeanEmailMapping::query()
                    ->where('is_active', true)
                    ->where('college_id', $user->college_id)
                    ->first();
            }
            $unit = trim((string) ($user->college_office ?? ''));
            if ($unit === '') return null;
            return DeanEmailMapping::query()
                ->where('is_active', true)
                ->where('affiliation_type', DeanEmailMapping::TYPE_COLLEGE)
                ->where('affiliation_name', $unit)
                ->first();
        }

        if ($user->office_id) {
            return DeanEmailMapping::query()
                ->where('is_active', true)
                ->where('office_id', $user->office_id)
                ->first();
        }

        $unit = trim((string) ($user->college_office ?? ''));
        if ($unit === '') return null;
        return DeanEmailMapping::query()
            ->where('is_active', true)
            ->where('affiliation_type', DeanEmailMapping::TYPE_OFFICE_DEPARTMENT)
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
     * After the requester successfully verifies email: dean queue vs librarian queue.
     */
    public static function statusAfterRequesterConfirmsEmail(Reservation $reservation): string
    {
        return self::resolveActiveDeanMapping($reservation) !== null
            ? Reservation::STATUS_PENDING_DEAN_APPROVAL
            : Reservation::STATUS_PENDING_APPROVAL;
    }

    /**
     * Staff who receive the librarian queue email (approve permission). Dean approver is excluded when they are also staff.
     *
     * @return array<string, string> normalized lowercase email => send address
     */
    public static function staffQueueApproverEmails(Reservation $reservation): array
    {
        $reservation->loadMissing('space', 'user');
        $out = [];
        foreach (User::with('role')->cursor() as $userRow) {
            if ($userRow->role && $userRow->role->hasPermission('reservation.approve')) {
                $e = trim((string) $userRow->email);
                if ($e !== '') {
                    $out[strtolower($e)] = $e;
                }
            }
        }

        $mapping = self::resolveActiveDeanMapping($reservation);
        if ($mapping !== null) {
            $dean = strtolower(trim((string) $mapping->approver_email));
            if ($dean !== '' && isset($out[$dean])) {
                unset($out[$dean]);
            }
        }

        return $out;
    }

    public static function sendDeanReviewRequestEmail(Reservation $reservation): void
    {
        $reservation->loadMissing('space', 'user');
        $mapping = self::resolveActiveDeanMapping($reservation);
        if ($mapping === null) {
            return;
        }
        $email = trim((string) $mapping->approver_email);
        if ($email === '') {
            return;
        }
        Mail::to($email)->send(new ReservationDeanReviewRequestMail($reservation));
    }

    public static function sendStaffLibrarianQueueNotifications(Reservation $reservation): void
    {
        foreach (self::staffQueueApproverEmails($reservation) as $email) {
            Mail::to($email)->send(new ReservationPendingApprovalAdminMail($reservation));
        }
    }

    /**
     * Called immediately after status is set post email verification.
     */
    public static function dispatchPostUserVerificationNotifications(Reservation $reservation): void
    {
        $reservation->loadMissing('space', 'user');
        if ($reservation->status === Reservation::STATUS_PENDING_DEAN_APPROVAL) {
            self::sendDeanReviewRequestEmail($reservation);

            return;
        }
        if ($reservation->status === Reservation::STATUS_PENDING_APPROVAL) {
            self::sendStaffLibrarianQueueNotifications($reservation);
        }
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
}
