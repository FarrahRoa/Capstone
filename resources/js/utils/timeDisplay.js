/** Canonical civil timezone for this product (matches backend APP_TIMEZONE). */
export const BOOKING_TIMEZONE = 'Asia/Manila';

function asDate(isoOrDate) {
    return isoOrDate instanceof Date ? isoOrDate : new Date(isoOrDate);
}

const displayDateFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: BOOKING_TIMEZONE,
    month: 'short',
    day: '2-digit',
    year: 'numeric',
});

/**
 * e.g. Apr 06 2026
 */
export function formatDisplayDate(isoOrDate) {
    const d = asDate(isoOrDate);
    const parts = displayDateFormatter.formatToParts(d);
    const mo = parts.find((p) => p.type === 'month')?.value ?? '';
    const day = parts.find((p) => p.type === 'day')?.value ?? '';
    const yr = parts.find((p) => p.type === 'year')?.value ?? '';
    return `${mo} ${day} ${yr}`.trim();
}

/**
 * e.g. 6:00 PM (12-hour, no 24h + AM/PM mix)
 */
export function formatDisplayTime(isoOrDate) {
    const d = asDate(isoOrDate);
    return new Intl.DateTimeFormat('en-US', {
        timeZone: BOOKING_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(d);
}

/**
 * Reservation window in Manila time, e.g. Apr 16 2026 · 9:30 AM – 5:30 PM
 */
export function formatReservationRange(startAt, endAt) {
    const start = asDate(startAt);
    const end = asDate(endAt);
    const d1 = formatDisplayDate(start);
    const d2 = formatDisplayDate(end);
    if (d1 === d2) {
        return `${d1} · ${formatDisplayTime(start)} – ${formatDisplayTime(end)}`;
    }
    return `${d1} · ${formatDisplayTime(start)} – ${d2} · ${formatDisplayTime(end)}`;
}

/**
 * Audit / activity timestamps.
 */
export function formatLogTime(isoOrDate) {
    const d = asDate(isoOrDate);
    return `${formatDisplayDate(d)} · ${formatDisplayTime(d)}`;
}
