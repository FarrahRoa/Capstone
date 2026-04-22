/** Manila wall-clock hour options for reservation pickers (24h). */
export const RESERVATION_TIME_HOUR_CHOICES = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));

/** Only :00 and :30 — used by reservation time UI and tests. */
export const RESERVATION_TIME_MINUTE_CHOICES = ['00', '30'];

function pad2(n) {
    return String(n).padStart(2, '0');
}

/**
 * Parse HH:mm (loose) into hour/minute for half-hour-only controls.
 * Non-half minutes map to :00 for display (caller should keep API-stored times valid).
 *
 * @param {string|null|undefined} value
 * @returns {{ hour: string, minute: '00'|'30' }}
 */
export function splitHalfHourWallClockHhmm(value) {
    const m = String(value ?? '').trim().match(/^(\d{1,2}):(\d{2})$/);
    if (!m) {
        return { hour: '09', minute: '00' };
    }
    let h = Math.trunc(Number(m[1]));
    if (!Number.isFinite(h)) {
        h = 9;
    }
    h = Math.min(23, Math.max(0, h));
    const rawMin = Number(m[2]);
    const minute = rawMin === 30 ? '30' : '00';
    return { hour: pad2(h), minute };
}

/**
 * @param {string} hour
 * @param {string} minute
 * @returns {string}
 */
export function joinHalfHourWallClockHhmm(hour, minute) {
    const h = pad2(Math.min(23, Math.max(0, Math.trunc(Number(hour)) || 0)));
    const mm = minute === '30' ? '30' : '00';
    return `${h}:${mm}`;
}
