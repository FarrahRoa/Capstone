import {
    manilaHhmmFromIso,
    manilaReservationDateTimePayload,
    manilaYmdFromInstant,
} from './manilaTime';

export const HALF_HOUR_HHMM_RE = /^\d{2}:(00|30)$/;

/**
 * Accepts optional `start_time` / `end_time` query values (e.g. from the booking calendar).
 * Returns normalized HH:mm on :00 / :30 only; otherwise null so callers keep safe defaults.
 *
 * @param {string|null|undefined} value
 * @returns {string|null}
 */
export function halfHourHhmmFromOptionalQueryParam(value) {
    if (value == null || typeof value !== 'string') {
        return null;
    }
    const m = value.trim().match(/^(\d{1,2}):(\d{2})$/);
    if (!m) {
        return null;
    }
    const h = Number(m[1]);
    const min = Number(m[2]);
    if (!Number.isInteger(h) || h < 0 || h > 23 || !Number.isInteger(min)) {
        return null;
    }
    if (min !== 0 && min !== 30) {
        return null;
    }
    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
}

/**
 * @param {{ slug?: string, type?: string }|null|undefined} space
 * @returns {'avr_range'|'half_hour_details'|'standard'}
 */
export function bookingKindFromSpace(space) {
    if (!space) {
        return 'standard';
    }
    const slug = space.slug;
    const isAvrOrLobby = slug === 'avr' || slug === 'lobby';
    if (isAvrOrLobby) {
        return 'avr_range';
    }
    const t = space.type;
    if (t === 'confab' || t === 'medical_confab' || t === 'lecture') {
        return 'half_hour_details';
    }
    return 'standard';
}

/**
 * Map stored reservation instants into the same wall-clock field shape as the New Reservation form.
 *
 * @param {'avr_range'|'half_hour_details'|'standard'} kind
 * @param {string|Date} startIso
 * @param {string|Date} endIso
 * @returns {Record<string, unknown>}
 */
export function wallClockFieldsFromInstants(kind, startIso, endIso) {
    const start = new Date(startIso);
    const end = new Date(endIso);
    if (kind === 'avr_range') {
        return {
            kind,
            rangeStartDate: manilaYmdFromInstant(start),
            rangeStartTime: manilaHhmmFromIso(start),
            rangeEndDate: manilaYmdFromInstant(end),
            rangeEndTime: manilaHhmmFromIso(end),
        };
    }
    if (kind === 'half_hour_details') {
        return {
            kind,
            date: manilaYmdFromInstant(start),
            rangeStartTime: manilaHhmmFromIso(start),
            rangeEndTime: manilaHhmmFromIso(end),
        };
    }
    return {
        kind,
        date: manilaYmdFromInstant(start),
        startTime: manilaHhmmFromIso(start),
        endTime: manilaHhmmFromIso(end),
    };
}

/**
 * @param {{ space?: { slug?: string, type?: string } }} reservation
 */
export function initialWallClockFieldsFromReservation(reservation) {
    const kind = bookingKindFromSpace(reservation?.space);
    return wallClockFieldsFromInstants(kind, reservation.start_at, reservation.end_at);
}

/**
 * @param {'avr_range'|'half_hour_details'|'standard'} kind
 * @param {Record<string, string>} fields
 * @returns {string|null} error message or null when valid
 */
export function validateHalfHourTimesForKind(kind, fields) {
    const bad = () => 'Times must use half-hour boundaries only (:00 or :30).';
    const check = (hhmm) => (HALF_HOUR_HHMM_RE.test(hhmm) ? null : bad());

    if (kind === 'avr_range') {
        return check(fields.rangeStartTime) || check(fields.rangeEndTime);
    }
    if (kind === 'half_hour_details') {
        return check(fields.rangeStartTime) || check(fields.rangeEndTime);
    }
    return check(fields.startTime) || check(fields.endTime);
}

/**
 * @param {'avr_range'|'half_hour_details'|'standard'} kind
 * @param {Record<string, string>} fields
 * @returns {{ start_at: string, end_at: string }}
 */
export function buildStartEndPayloadFromWallClock(kind, fields) {
    if (kind === 'avr_range') {
        return {
            start_at: manilaReservationDateTimePayload(fields.rangeStartDate, fields.rangeStartTime),
            end_at: manilaReservationDateTimePayload(fields.rangeEndDate, fields.rangeEndTime),
        };
    }
    if (kind === 'half_hour_details') {
        return {
            start_at: manilaReservationDateTimePayload(fields.date, fields.rangeStartTime),
            end_at: manilaReservationDateTimePayload(fields.date, fields.rangeEndTime),
        };
    }
    return {
        start_at: manilaReservationDateTimePayload(fields.date, fields.startTime),
        end_at: manilaReservationDateTimePayload(fields.date, fields.endTime),
    };
}
