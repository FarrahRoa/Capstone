/** Institutional booking cutoff — Manila wall clock, 24h comparisons only. */
export const BOOKING_CUTOFF_HHMM = '16:30';

export const BOOKING_CUTOFF_MINUTES = 16 * 60 + 30;

export function wallClockToMinutes(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map((x) => Number(x));
    if (!Number.isFinite(h) || !Number.isFinite(m)) return 0;
    return h * 60 + m;
}

export function isWallClockAtOrAfterBookingCutoff(hhmm) {
    return wallClockToMinutes(hhmm) >= BOOKING_CUTOFF_MINUTES;
}

export function isSlotStartAtOrAfterBookingCutoff(hour, minute) {
    return hour * 60 + minute >= BOOKING_CUTOFF_MINUTES;
}

export function formatHhmm12(hhmm) {
    const [h, m] = String(hhmm || '00:00').split(':').map((x) => Number(x));
    if (!Number.isFinite(h) || !Number.isFinite(m)) return hhmm;
    const d = new Date(2000, 0, 1, h, m, 0);
    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(d);
}

/**
 * @param {{ day_start?: string }} operatingHoursConfig
 */
export function cutoffBlackoutMessage(operatingHoursConfig) {
    const resume = formatHhmm12(operatingHoursConfig?.day_start || '06:00');
    return `Reservations are unavailable after 4:30 PM. Booking resumes at ${resume} tomorrow.`;
}
