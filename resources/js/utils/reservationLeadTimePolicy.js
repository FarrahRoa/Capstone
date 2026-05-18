import { BOOKING_TIMEZONE } from './timeDisplay';
import {
    isPastReservationSubmissionCutoff,
    reservationCutoffValidationMessage,
} from './bookingSlotCutoff';
import { MANILA_OFFSET, manilaYmdFromInstant, shiftManilaYmd } from './manilaTime';

/** @deprecated Prefer {@link SAME_DAY_DENIED_MESSAGE} or {@link cutoffBlackoutMessage}. */
export const RESERVATION_LEAD_TIME_DENIED_MESSAGE =
    'Reservations for the selected date are no longer allowed based on system rules.';

export const SAME_DAY_DENIED_MESSAGE = 'Same-day reservations are not allowed.';

/** @deprecated Use {@link reservationCutoffValidationMessage}. */
export const CUTOFF_BLACKOUT_MESSAGE =
    'Reservations are closed for today. You may reserve again starting 9:00 AM tomorrow.';

/**
 * Minutes since Manila midnight for an instant (for comparing to the 4:30 PM cutoff).
 *
 * Manila has no DST; wall clock is authoritative for business rules.
 */
export function manilaMinutesSinceMidnight(date = new Date()) {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: BOOKING_TIMEZONE,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);
    const hh = Number(parts.find((p) => p.type === 'hour')?.value ?? 0);
    const mm = Number(parts.find((p) => p.type === 'minute')?.value ?? 0);
    return hh * 60 + mm;
}

/**
 * Submission closed window: from 4:30 PM inclusive until 9:00 AM exclusive (Manila wall clock).
 *
 * @param {Date} [date]
 */
export function isInEveningBookingBlackout(date = new Date()) {
    return isPastReservationSubmissionCutoff(date);
}

/**
 * @deprecated Use {@link isInEveningBookingBlackout}. Kept for callers that only checked post-cutoff.
 */
export function isAfterNextDayReservationCutoff(date = new Date()) {
    return isInEveningBookingBlackout(date);
}

/** Only slug `admin` bypasses strict lead-time rules (matches `User::isAdmin()` server-side). */
export function exemptFromReservationLeadTime(user) {
    return String(user?.role?.slug || '').toLowerCase() === 'admin';
}

export function manilaTomorrowYmd(refDate = new Date()) {
    return shiftManilaYmd(manilaYmdFromInstant(refDate), 1);
}

/**
 * Default calendar focus for standard users (tomorrow). Today remains selectable for viewing slots.
 */
export function getMinimumBookableManilaYmd(refDate = new Date()) {
    return manilaTomorrowYmd(refDate);
}

/**
 * Reason the Book action must be disabled for a selected Manila day (null = allowed).
 *
 * @param {string} selectedYmd
 * @param {Date} [refDate]
 * @param {{ day_start?: string }} [operatingHoursConfig]
 */
export function policyBlockReasonForManilaReservationDay(
    selectedYmd,
    refDate = new Date(),
    operatingHoursConfig = null,
    options = null
) {
    void operatingHoursConfig;
    if (isPastReservationSubmissionCutoff(refDate)) {
        return reservationCutoffValidationMessage();
    }
    const today = manilaYmdFromInstant(refDate);
    const allowSameDay = options?.allowSameDay === true;
    if (!allowSameDay && selectedYmd === today) {
        return SAME_DAY_DENIED_MESSAGE;
    }
    return null;
}

export function canBookManilaCalendarDay(selectedYmd, refDate = new Date(), operatingHoursConfig = null) {
    return policyBlockReasonForManilaReservationDay(selectedYmd, refDate, operatingHoursConfig) === null;
}

function rangeOverlapsManilaCalendarDayIso(startIso, endIso, dayYmd) {
    const day0 = Date.parse(`${dayYmd}T00:00:00${MANILA_OFFSET}`);
    const day1 = day0 + 86400000;
    const s = Date.parse(startIso);
    const e = Date.parse(endIso);
    if (!Number.isFinite(s) || !Number.isFinite(e)) {
        return true;
    }
    return s < day1 && e > day0;
}

/**
 * True when `[start_iso, end_iso)` must be rejected for standard users (matches server).
 */
export function standardUserBookingViolatesLeadTimeRules(
    startIso,
    endIso,
    refDate = new Date(),
    options = null
) {
    if (isPastReservationSubmissionCutoff(refDate)) {
        return true;
    }
    const today = manilaYmdFromInstant(refDate);
    const allowSameDay = options?.allowSameDay === true;
    if (!allowSameDay && rangeOverlapsManilaCalendarDayIso(startIso, endIso, today)) {
        return true;
    }
    return false;
}

/**
 * User-facing message when {@link standardUserBookingViolatesLeadTimeRules} is true.
 */
export function messageForLeadTimeViolation(
    startIso,
    endIso,
    refDate = new Date(),
    operatingHoursConfig = null,
    options = null
) {
    void operatingHoursConfig;
    if (isPastReservationSubmissionCutoff(refDate)) {
        return reservationCutoffValidationMessage();
    }
    const today = manilaYmdFromInstant(refDate);
    const allowSameDay = options?.allowSameDay === true;
    if (!allowSameDay && rangeOverlapsManilaCalendarDayIso(startIso, endIso, today)) {
        return SAME_DAY_DENIED_MESSAGE;
    }
    return RESERVATION_LEAD_TIME_DENIED_MESSAGE;
}
