<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationNumberCounter;
use App\Models\Space;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Assigns human-readable reservation numbers (CS1, 1AVR, MEDCS2, …) per category counter.
 */
class ReservationReadableIdService
{
    public const CATEGORY_CS = 'CS';

    public const CATEGORY_CE = 'CE';

    public const CATEGORY_AVR = 'AVR';

    public const CATEGORY_LOBBY = 'L';

    public const CATEGORY_MEDICAL_STUDENT = 'MEDCS';

    public const CATEGORY_LECTURE = 'LS';

    /** @var list<string> */
    public const PREFIX_CATEGORIES = [
        self::CATEGORY_CS,
        self::CATEGORY_CE,
        self::CATEGORY_MEDICAL_STUDENT,
    ];

    /** @var list<string> */
    public const SUFFIX_CATEGORIES = [
        self::CATEGORY_AVR,
        self::CATEGORY_LOBBY,
        self::CATEGORY_LECTURE,
    ];

    /**
     * Assign a readable ID after the reservation row exists. No-op when the space has no category (e.g. boardroom).
     *
     * @throws ValidationException when the user is not eligible for a readable ID on this space
     */
    public function assignAfterSuccessfulCreate(Reservation $reservation, Space $space, User $user): void
    {
        if ($reservation->reservation_number !== null) {
            return;
        }

        $category = $this->resolveCategory($space, $user);
        if ($category === null) {
            return;
        }

        $blocked = $user->roomReservationBlockedMessage($space);
        if ($blocked !== null) {
            throw ValidationException::withMessages([
                'space_id' => [$blocked],
            ]);
        }

        $sequence = $this->allocateNextSequence($category);
        $display = $this->formatDisplayId($category, $sequence);

        $reservation->update([
            'reservation_number' => $display,
            'reservation_sequence' => $sequence,
            'reservation_category' => $category,
        ]);
    }

    /**
     * Backfill readable IDs for legacy rows (admin approve, override fixtures, etc.).
     */
    public function assignIfMissing(Reservation $reservation): void
    {
        if ($reservation->reservation_number !== null) {
            return;
        }

        $reservation->loadMissing('user', 'space');
        if ($reservation->user === null || $reservation->space === null) {
            return;
        }

        $this->assignAfterSuccessfulCreate($reservation, $reservation->space, $reservation->user);
    }

    public function formatDisplayId(string $category, int $sequence): string
    {
        if (in_array($category, self::PREFIX_CATEGORIES, true)) {
            return $category.$sequence;
        }

        if (in_array($category, self::SUFFIX_CATEGORIES, true)) {
            return $sequence.$category;
        }

        throw new \InvalidArgumentException("Unknown reservation category: {$category}");
    }

    public function resolveCategory(Space $space, User $user): ?string
    {
        $audience = $this->resolveAudience($user);
        if ($audience === null) {
            return null;
        }

        $spaceType = (string) ($space->type ?? '');

        if ($spaceType === Space::TYPE_CONFAB) {
            return $audience === 'student' ? self::CATEGORY_CS : self::CATEGORY_CE;
        }

        if ($spaceType === Space::TYPE_MEDICAL_CONFAB) {
            return $audience === 'student' ? self::CATEGORY_MEDICAL_STUDENT : null;
        }

        if ($audience !== 'employee') {
            return null;
        }

        return match ($spaceType) {
            Space::TYPE_AVR => self::CATEGORY_AVR,
            Space::TYPE_LOBBY => self::CATEGORY_LOBBY,
            Space::TYPE_LECTURE => self::CATEGORY_LECTURE,
            default => null,
        };
    }

    /**
     * @return 'student'|'employee'|null
     */
    public function resolveAudience(User $user): ?string
    {
        if ($user->isAdmin()) {
            return 'employee';
        }

        $userType = $user->user_type ?? User::getUserTypeFromEmail((string) $user->email);

        if ($userType === User::USER_TYPE_STUDENT) {
            return 'student';
        }

        if ($userType === User::USER_TYPE_FACULTY_STAFF) {
            return 'employee';
        }

        return null;
    }

    private function allocateNextSequence(string $category): int
    {
        ReservationNumberCounter::query()->insertOrIgnore([
            'category' => $category,
            'last_sequence' => 0,
        ]);

        /** @var ReservationNumberCounter $row */
        $row = ReservationNumberCounter::query()
            ->where('category', $category)
            ->lockForUpdate()
            ->firstOrFail();

        $next = ((int) $row->last_sequence) + 1;
        $row->update(['last_sequence' => $next]);

        return $next;
    }
}
